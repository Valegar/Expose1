<?php

use Ideys\Content\ContentFactory;
use Ideys\User\UserProvider;
use Ideys\User\ProfileType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints as Assert;

$frontendController = $app['controllers_factory'];

$frontendContent = function (Request $request, $slug = null) use ($app) {

    $contentFactory = new ContentFactory($app);

    if (null === $slug) {
        $section = $contentFactory->findHomepage($slug);
    } else {
        $section = $contentFactory->findSectionBySlug($slug);
    }

    if (!$section) {
        throw new Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
    }

    if ($section->isPrivate() && (false === $app['security']->isGranted('ROLE_USER'))
     || $section->isClosed()) {
        $app['session']
            ->getFlashBag()
            ->add('warning', $app['translator']->trans('section.unavailable'));
        return $app->redirect($app['url_generator']->generate('homepage'));
    }

    $formView = null;
    if ($section instanceof Ideys\Content\Section\Form) {
        $form = $section->generateFormFields($app['form.factory']);
        if ($section->checkSubmitedForm($request, $form)) {
            $validationMessage = $section->getParameter('validation_message');
            $app['session']
                ->getFlashBag()
                ->add('success', empty($validationMessage) ?
                        $app['translator']->trans('form.validation.message.default'):
                    $validationMessage);
            return $app->redirect($app['url_generator']->generate('section', array('slug' => $slug)));
        }
        $formView = $form->createView();
    }

    return $app['twig']->render('frontend/'.$section->type.'/'.$section->type.'.html.twig', array(
      'section' => $section,
      'form' => $formView,
    ));
};

$frontendController->get('/', $frontendContent)
->bind('homepage')
;

$frontendController->match('/theme/{slug}', $frontendContent)
->bind('section')
->method('GET|POST')
;

$frontendController->match('/private/theme/{slug}', $frontendContent)
->bind('section_private')
->method('GET|POST')
;

$frontendController->match('/contact', function (Request $request) use ($app) {

    $messaging = new \Ideys\Messaging($app['db']);

    $form = $app['form.factory']->createBuilder('form')
        ->add('name', 'text', array(
            'constraints'   => array(
                new Assert\Length(array('min' => 3)),
                new Assert\NotBlank(),
            ),
            'label'         => 'contact.name',
        ))
        ->add('email', 'email', array(
            'constraints'   => array(
                new Assert\Email(),
                new Assert\NotBlank(),
            ),
            'label'         => 'contact.email',
        ))
        ->add('message', 'textarea', array(
            'constraints'   => array(
                new Assert\Length(array('min' => 10)),
                new Assert\NotBlank(),
            ),
            'label'         => 'contact.message',
        ))
        ->getForm();

    $form->handleRequest($request);
    if ($form->isValid()) {
        $data = $form->getData();
        $messaging->create(
            $data['name'],
            $data['email'],
            $data['message']
        );
        $app['session']
            ->getFlashBag()
            ->add('success', $app['translator']->trans('contact.info.sent'));
        return $app->redirect($app['url_generator']->generate('contact'));
    }

    return $app['twig']->render('frontend/contact.html.twig', array(
        'form' => $form->createView(),
    ));
})
->bind('contact')
->method('GET|POST')
;

$frontendController->match('/profile', function (Request $request, $id = null) use ($app) {

    $userProvider = new UserProvider($app['db'], $app['session']);
    
    $profile = $app['session']->get('profile');

    $profileType = new ProfileType($app['form.factory']);
    $form = $profileType->form($profile);

    $form->handleRequest($request);
    if ($form->isValid()) {
        $userProvider->persist($app['security.encoder_factory'], $profile);
        $app['session']
            ->getFlashBag()
            ->add('default', $app['translator']->trans('user.updated'));
        return $app->redirect($app['url_generator']->generate('user_profile'));
    }

    return $app['twig']->render('frontend/userProfile.html.twig', array(
        'form' => $form->createView(),
    ));
})
->value('id', null)
->assert('id', '\d+')
->bind('user_profile')
->method('GET|POST')
->secure('ROLE_USER')
;

$frontendController->assert('_locale', implode('|', $app['languages']));

return $frontendController;
