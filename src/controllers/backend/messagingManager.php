<?php

use Ideys\Messaging\Messaging;

$messagingManagerController = $app['controllers_factory'];

$messagingManagerController->get('/', function () use ($app) {

    $messaging = new Messaging($app['db']);
    $messages = $messaging->findAll();
    $count = $messaging->countAll();

    return $app['twig']->render('backend/messagingManager/messagingList.html.twig', array(
        'messages' => $messages,
        'count' => $count,
        'is_archive' => false,
    ));
})
->bind('admin_messaging_manager')
;

$messagingManagerController->get('/archive', function () use ($app) {

    $messaging = new Messaging($app['db']);
    $messages = $messaging->findArchived();
    $count = $messaging->countAll();

    return $app['twig']->render('backend/messagingManager/messagingList.html.twig', array(
        'messages' => $messages,
        'count' => $count,
        'is_archive' => true,
    ));
})
->bind('admin_messaging_manager_archive')
;

$messagingManagerController->get('/{id}/archive', function ($id) use ($app) {

    $messaging = new Messaging($app['db']);
    $messaging->archive($id);

    return $app->redirect($app['url_generator']->generate('admin_messaging_manager'));
})
->assert('id', '\d+')
->bind('admin_messaging_manager_archive_item')
;

$messagingManagerController->get('/{id}/delete', function ($id) use ($app) {

    $messaging = new Messaging($app['db']);
    $messaging->delete($id);

    return $app->redirect($app['url_generator']->generate('admin_messaging_manager'));
})
->assert('id', '\d+')
->bind('admin_messaging_manager_delete')
;

$messagingManagerController
->assert('_locale', implode('|', $app['languages']))
->secure('ROLE_ADMIN')
;

return $messagingManagerController;
