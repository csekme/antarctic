<?php
namespace Framework;
interface InterceptorInterface
{
    /**
     * @param Request $request
     * @return Response
     */
    public function invoke(Request $request, Response $response): Response;
}