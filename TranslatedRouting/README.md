A simple translation routing config

// the service, class can be where you desire

services:   
    costum_router:
        class: MyBundle\ModelBundle\Manager\CostumRouter
        arguments: [@router, @service_container, "%kernel.default_locale%"]
    twig_router:
        class: MyBundle\ModelBundle\Twig\CostumRouter
        arguments: [@costum_router]
        tags:
          - { name: twig.extension }


// YML Routing, main routing
// other routings could be like this if no prefix!!
// app/config/routing.yml
mybundle_cart:
    resource: "@MyBundleCartBundle/Resources/config/routing.yml"
    prefix:   /{_locale}/{_translated}
    requirements:
        _locale:      en|es|fr
        _translated:  cart|carrito|panier


// for prefixed bundles, like the cart / name 
// app/mybundle/Resources/routing.yml
mybundle_cart_homepage:
    path:     /{_action_translation}/{name}
    defaults: { _controller: MyBundleCartBundle:Default:index }
    requirements:
        _action_translation:  hello|hola|salut
	
	
// In a Controller

$router = $this->get('costum_router');
 
// simple routing, no translation     
echo $router->generate('br_api_database');
// /api/db-update

// rute with params {name}       
echo $router->generate('eunasa_cart_homepage', array('name' => 'jack'));
// /es/carrito/hola/jack




echo $router->generate('br_cms_registratione');
/es/registro    por que estamos en el idioma

// TWIG extension
{{ costum_router('br_cms_registration') }}
/es/registro

// con params
{{ costum_router('eunasa_cart_homepage', {'name' : 'jack'}) }}
/es/carrito/hola/jack 