<?php

namespace React\Gifsocket;

class Router
{
    private $routes;

    public function __construct(array $routes)
    {
        $this->routes = $routes;
    }

    public function __invoke($request, $response)
    {
        foreach ($this->routes as $pattern => $controller) {
            if ($this->requestMatchesPattern($request, $pattern)) {
                $controller($request, $response);
                return;
            }
        }

        $this->handleNotFound($request, $response);
    }

    protected function requestMatchesPattern($request, $pattern)
    {
        return $pattern === $request->getPath();
    }

    protected function handleNotFound($request, $response)
    {
        $response->writeHead(404, ['Content-Type' => 'text/plain']);
        $response->end("We are sorry to inform you that the requested resource does not exist.");
    }
}
