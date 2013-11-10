<?php

$backendController = $app['controllers_factory'];

$backendController->get('/', function () use ($app) {

    return $app['twig']->render('backend/contentManager.html.twig');
})
->bind('admin_content_manager')
;

$backendController->get('/logout', function () use ($app) {

    $app['session']->remove('user');
    return $app->redirect($app['url_generator']->generate('homepage'));
})
->bind('admin_logout')
;

$backendController->assert('_locale', implode('|', $app['languages']));

return $backendController;
