<?php
require_once 'vendor/autoload.php';

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use League\Route\RouteCollection;

$router = new RouteCollection();
$request = Request::createFromGlobals();

$hService = new \GuzzleHttp\Client([
    'base_uri' => 'https://usw-hotels-service.herokuapp.com'
]);

$rService = new \GuzzleHttp\Client([
    'base_uri' => 'https://sheltered-cliffs-5863.herokuapp.com/' // @todo replace URL
]);

$router->addRoute('GET', '/hotels', function () use ($hService, $rService, $request) {
    $expandString = $request->get('expand');
    $expandList = explode(',', $expandString);

    try {
        $listing = $hService->get('/hotels')->getBody()->getContents();
        $items = json_decode($listing);

        foreach ($items as $index => $item) {
            if (!is_object($item)) {
                throw new InvalidArgumentException('Invalid response from hotels query.');
            }

            if (count($expandList) > 0 && in_array('ratings', $expandList)) {
                $ratingsListingResponse = $rService->get('/ratings?hotel=' . $item->identifier);
                $items[$index]->{'ratings'} = $ratingsListingResponse->getBody()->getContents();

            } else {
                $items[$index]->{'@ratings'} = '/ratings?hotel=' . $item->identifier;
            }
        }

        return new JsonResponse($items, 200);

    } catch (\GuzzleHttp\Exception\BadResponseException $e) {
        return new JsonResponse($e->getResponse()->getBody()->getContents(), 400);
    }
});

$router->addRoute('GET', '/rooms', function () use ($hService) {
    try {
        $listing = $hService->get('/rooms')->getBody()->getContents();
        return new JsonResponse(json_decode($listing), 200);

    } catch (\GuzzleHttp\Exception\BadResponseException $e) {
        return new JsonResponse($e->getResponse()->getBody()->getContents(), 400);
    }
});

$router->addRoute('GET', '/ratings', function () use ($rService) {
    try {
        $listing = $rService->get('/ratings')->getBody()->getContents();
        return new JsonResponse(json_decode($listing), 200);

    } catch (\GuzzleHttp\Exception\BadResponseException $e) {
        return new JsonResponse($e->getResponse()->getBody()->getContents(), 400);
    }
});

$router->addRoute('GET', '/ratings/{id}', function () use ($rService, $args) {
    try {
        $listing = $rService->get('/ratings/' . $args['id'])->getBody()->getContents();
        return new JsonResponse(json_decode($listing), 200);

    } catch (\GuzzleHttp\Exception\BadResponseException $e) {
        return new JsonResponse($e->getResponse()->getBody()->getContents(), 400);
    }
});

$router->addRoute('GET', '/hotel/{hotelIdentifier}/ratings', function () use ($hService, $rService, $args) {
    try {
        $listing = $rService->get('/ratings?identifier=' . $args['hotelIdentifier'])->getBody()->getContents();
        return new JsonResponse(json_decode($listing), 200);

    } catch (\GuzzleHttp\Exception\BadResponseException $e) {
        return new JsonResponse($e->getResponse()->getBody()->getContents(), 400);
    }
});

$errorMessage = '';

try {
    $response = $router->getDispatcher()->dispatch($request->getMethod(), $request->getPathInfo());

} catch (\League\Route\Http\Exception\NotFoundException $exception) {
    $response = new RedirectResponse('/hotels');

} catch (\Exception $e) {
    $response = new JsonResponse(['error' => $e->getMessage()], 422);
}

$response->send();
