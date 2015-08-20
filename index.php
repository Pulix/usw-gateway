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

$router->addRoute('GET', '/hotels', function (Request $request, $response, $args) use ($hService, $rService, $request) {
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

        $return = json_encode($items);

    } catch (\GuzzleHttp\Exception\BadResponseException $e) {
        $return = $e->getResponse()->getBody()->getContents();
        $response->setStatusCode(400);
    }

    return $response->setContent($return);
});

$router->addRoute('GET', '/rooms', function (Request $request, $response, $args) use ($hService) {
    try {
        $return = $hService->get('/rooms')->getBody()->getContents();

    } catch (\GuzzleHttp\Exception\BadResponseException $e) {
        $return = $e->getResponse()->getBody()->getContents();
        $response->setStatusCode(400);
    }

    return $response->setContent($return);
});

$router->addRoute('GET', '/ratings', function (Request $request, $response, $args) use ($rService) {
    try {
        $return = $rService->get($request->getBaseUrl())->getBody()->getContents();

    } catch (\GuzzleHttp\Exception\BadResponseException $e) {
        $return = $e->getResponse()->getBody()->getContents();
        $response->setStatusCode(400);
    }

    return $response->setContent($return);
});

$router->addRoute('GET', '/ratings/{id}', function (Request $request, $response, $args) use ($rService) {
    try {
        $listing = $rService->get($request->getBaseUrl())->getBody()->getContents();
        $return = $listing;

    } catch (\GuzzleHttp\Exception\BadResponseException $e) {
        $return = $e->getResponse()->getBody()->getContents();
        $response->setStatusCode(400);
    }

    return $response->setContent($return);
});

$router->addRoute('GET', '/hotel/{hotelIdentifier}', function (Request $request, $response, $args) use ($hService, $rService, $request) {
    $expandString = $request->get('expand');
    $expandList = explode(',', $expandString);

    try {
        $listing = $hService->get('/hotel/' . $args['hotelIdentifier'])->getBody()->getContents();
        $item = json_decode($listing);

        if (!is_object($item)) {
            throw new InvalidArgumentException('Invalid response from hotels query.');
        }

        if (count($expandList) > 0 && in_array('ratings', $expandList)) {
            $ratingsListingResponse = $rService->get('/ratings?hotel=' . $item->identifier);
            $item->{'ratings'} = $ratingsListingResponse->getBody()->getContents();

        } else {
            $item->{'@ratings'} = '/ratings?hotel=' . $item->identifier;
        }

        $return = json_encode($item);

    } catch (\GuzzleHttp\Exception\BadResponseException $e) {
        $return = $e->getResponse()->getBody()->getContents();
        $response->setStatusCode(400);
    }

    return $response->setContent($return);
});

$router->addRoute('GET', '/hotel/{hotelIdentifier}/ratings', function (Request $request, JsonResponse $response, $args) use ($hService, $rService) {
    try {
        $return = $rService->get('/ratings?identifier=' . $args['hotelIdentifier'])->getBody()->getContents();

    } catch (\GuzzleHttp\Exception\BadResponseException $e) {
        $return = $e->getResponse()->getBody()->getContents();
        $response->setStatusCode(400);
    }

    return $response->setContent($return);
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
