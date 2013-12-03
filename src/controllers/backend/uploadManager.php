<?php

use Symfony\Component\HttpFoundation\Request;

$uploadManagerController = $app['controllers_factory'];

$uploadManagerController->post('/', function (Request $request) use ($app) {

    $uploadedFiles = $request->files->all();
    $sectionId = (int) $request->request->get('sectionId');
    if (0 == $sectionId) {
        $sectionId = null;
    }
    $content = new Content($app['db']);
    $jsonResponse = array();

    foreach ($uploadedFiles['files'] as $file) {
        $data = array(
            'type' => $file->getMimeType(),
            'title' => $file->getClientOriginalName(),
            'description' => null,
            'content' => null,
            'language' => 'fr',
        );
        $fileExt = $file->guessClientExtension();
        $realExt = $file->guessExtension();// from mime type
        $fileSize = $file->getClientSize();
        $data['path'] = uniqid('expose').'.'.$fileExt;

        $file->move($app['gallery.dir'], $data['path']);

        $content->addItem(
                $sectionId,
                $data['type'],
                $data['path'],
                $data['title'],
                $data['description'],
                $data['content'],
                $data['language']
        );

        $jsonResponse[] = array(
            'path' => $data['path'],
            'id' => $sectionId,
        );
    }

    return $app->json($jsonResponse);
})
->bind('admin_upload_manager_upload')
;

$uploadManagerController->assert('_locale', implode('|', $app['languages']));

return $uploadManagerController;
