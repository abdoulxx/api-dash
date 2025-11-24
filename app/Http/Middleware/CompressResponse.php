<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CompressResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var \Symfony\Component\HttpFoundation\Response $response */
        $response = $next($request);

        if (! function_exists('gzencode')) {
            return $response;
        }

        if (stripos($request->header('Accept-Encoding', ''), 'gzip') === false) {
            return $response;
        }

        if (! $this->isCompressible($response)) {
            return $response;
        }

        $response->setContent(gzencode($response->getContent(), 5));
        $response->headers->set('Content-Encoding', 'gzip');
        $response->headers->set('Vary', 'Accept-Encoding');
        $response->headers->set('Content-Length', strlen($response->getContent()));

        return $response;
    }

    private function isCompressible(Response $response): bool
    {
        // Ne pas compresser les BinaryFileResponse, StreamedResponse, etc.
        if ($response instanceof \Symfony\Component\HttpFoundation\BinaryFileResponse) {
            return false;
        }

        if ($response instanceof \Symfony\Component\HttpFoundation\StreamedResponse) {
            return false;
        }

        if ($response->headers->has('Content-Encoding')) {
            return false;
        }

        // Vérifier si on peut obtenir le contenu (les BinaryFileResponse ne le permettent pas)
        try {
            $response->getContent();
        } catch (\LogicException $e) {
            // Si on ne peut pas obtenir le contenu, ne pas compresser
            return false;
        }

        $contentType = $response->headers->get('Content-Type', '');

        return str_contains($contentType, 'application/json')
            || str_contains($contentType, 'text/');
    }
}




