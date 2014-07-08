A simple translation routing config

// the service, class can be where you desire

services:   
    MyBundle.costum_router:
        class: MyBundleBundle\Manager\CustomRouter
        arguments: [@router, @service_container, "%kernel.default_locale%"]

// App Routing to include the bundle
mybundle_route:
    resource: "@MyBundleBundle/Resources/config/routing.yml"
    prefix:   /{_locale}/{_translated}
    requirements:
        _locale:      en|es
        _translated:  my-prefix|mi-prefijo
		
// In you bundle resources config routing
mybundle_homepage:
    path:     /{_action_translation}/{name}
    defaults: { _controller: MyBundleBundle:Default:index }
    requirements:
        _action_translation:  hello|hola
		
		
		
$url = $router->generate('mybundle_homepage', array('name' => 'jack'));