<?php


namespace Core;

use Blog\Models\BlogManagerPDO;
use \Twig;
use Twig\TwigFunction;


class HTTPResponse
{

    public function addHeader(string $header)
    {
        header($header);
    }

    public static function redirect(string $location)
    {
        header('location: http://' . $_SERVER['HTTP_HOST'] . $location, true, 303);
        exit;
    }

    public function redirect404()
    {

    }

    /**
     * @param $template
     * @param array $args
     * @throws Twig\Error\LoaderError
     * @throws Twig\Error\RuntimeError
     * @throws Twig\Error\SyntaxError
     */
    public static function renderTemplate (string $template, array $args = [])
    {
        echo static::getTemplate($template, $args);
    }

    /**
     * @param string $template
     * @param array $args
     * @return string
     * @throws Twig\Error\LoaderError
     * @throws Twig\Error\RuntimeError
     * @throws Twig\Error\SyntaxError
     */
    public static function getTemplate (string $template, array $args = [])
    {
        static $twig = null;

        if ($twig === null) {
            $loader = new Twig\Loader\FilesystemLoader(dirname(__DIR__) . '/Templates');
            $twig = new Twig\Environment($loader, [
                //'cache' => '../cache'
            ]);
            $twig->addGlobal('path', 'http://' . $_SERVER['HTTP_HOST']);
            $twig->addGlobal('current_user', Auth::getUser());
            $twig->addGlobal('flash_messages', Flash::getMessages());
            $twig->addGlobal('blog', static::getBlog());
        }
        return $twig->render($template, $args);
    }


    public static function setCookie($name, $value = '', $expire = 0, $path = null, $domain = null, $secure = false, $httpOnly = true)
    {
        setCookie($name, $value, $expire, $path, $domain, $secure, $httpOnly);
    }

    private static function getBlog()
    {
        $config = Config::getInstance();
        $blogId = $config->get('blog_id') ? $config->get('blog_id') : 1;
        $blogManager = new BlogManagerPDO(PDOFactory::getPDOConnexion());
        return $blogManager->getData($blogId);
    }
}