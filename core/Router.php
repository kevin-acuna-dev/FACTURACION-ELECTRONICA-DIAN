<?php

class Router {
    private $routes = [];
    private $middlewares = [];
    
    public function get($path, $handler, $middleware = []) {
        $this->addRoute('GET', $path, $handler, $middleware);
    }
    
    public function post($path, $handler, $middleware = []) {
        $this->addRoute('POST', $path, $handler, $middleware);
    }
    
    public function put($path, $handler, $middleware = []) {
        $this->addRoute('PUT', $path, $handler, $middleware);
    }
    
    public function delete($path, $handler, $middleware = []) {
        $this->addRoute('DELETE', $path, $handler, $middleware);
    }
    
    public function group($prefix, $callback, $middleware = []) {
        $oldPrefix = $this->currentPrefix ?? '';
        $this->currentPrefix = $oldPrefix . $prefix;
        $oldMiddleware = $this->currentMiddleware ?? [];
        $this->currentMiddleware = array_merge($oldMiddleware, $middleware);
        
        if (is_callable($callback)) {
            $callback($this);
        }
        
        $this->currentPrefix = $oldPrefix;
        $this->currentMiddleware = $oldMiddleware;
    }
    
    private function addRoute($method, $path, $handler, $middleware = []) {
        $fullPath = ($this->currentPrefix ?? '') . $path;
        $fullMiddleware = array_merge($this->currentMiddleware ?? [], $middleware);
        
        $this->routes[] = [
            'method' => $method,
            'path' => $fullPath,
            'handler' => $handler,
            'middleware' => $fullMiddleware
        ];
    }
    
    public function dispatch() {
        $method = $_SERVER['REQUEST_METHOD'];
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        
        // Normalizar basePath
        $basePath = str_replace('/index.php', '', $_SERVER['SCRIPT_NAME']);
        $basePath = rtrim($basePath, '/');
        
        // Remover basePath de la URI
        if ($basePath && strpos($uri, $basePath) === 0) {
            $uri = substr($uri, strlen($basePath));
        }
        
        // Normalizar URI: asegurar que empiece con / y no termine con / (excepto raíz)
        $uri = '/' . trim($uri, '/');
        if ($uri === '//') {
            $uri = '/';
        }
        
        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }
            
            $pattern = $this->convertPathToRegex($route['path']);
            if (preg_match($pattern, $uri, $matches)) {
                array_shift($matches);
                
                foreach ($route['middleware'] as $middleware) {
                    if (!$this->runMiddleware($middleware)) {
                        return;
                    }
                }
                
                $this->runHandler($route['handler'], $matches);
                return;
            }
        }
        
        Response::error('Ruta no encontrada', 404);
    }
    
    private function convertPathToRegex($path) {
        $pattern = preg_replace('/\{(\w+)\}/', '([^/]+)', $path);
        // Permitir que la ruta termine opcionalmente con /
        if ($pattern !== '/') {
            $pattern = rtrim($pattern, '/');
        }
        return '#^' . $pattern . '/?$#';
    }
    
    private function runMiddleware($middleware) {
        if (is_string($middleware)) {
            if (function_exists($middleware)) {
                return $middleware();
            }
            $file = __DIR__ . '/../middleware/' . $middleware . '.php';
            if (file_exists($file)) {
                require_once $file;
                if (function_exists($middleware)) {
                    return $middleware();
                }
            }
            if (class_exists($middleware)) {
                $instance = new $middleware();
                if (method_exists($instance, 'handle')) {
                    return $instance->handle();
                }
            }
        }
        if (is_callable($middleware)) {
            return $middleware();
        }
        return true;
    }
    
    private function runHandler($handler, $params) {
        if (is_string($handler) && strpos($handler, '@') !== false) {
            list($class, $method) = explode('@', $handler);
            if (class_exists($class)) {
                $instance = new $class();
                if (method_exists($instance, $method)) {
                    call_user_func_array([$instance, $method], $params);
                    return;
                }
            }
        }
        if (is_callable($handler)) {
            call_user_func_array($handler, $params);
            return;
        }
        Response::error('Handler inválido', 500);
    }
}

