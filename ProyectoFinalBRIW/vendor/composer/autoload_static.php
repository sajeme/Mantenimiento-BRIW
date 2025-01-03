<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit83cc9a19dfdab90b1f6bd762de78b87e
{
    public static $files = array (
        '7b11c4dc42b3b3023073cb14e519683c' => __DIR__ . '/..' . '/ralouphie/getallheaders/src/getallheaders.php',
        '6e3fae29631ef280660b3cdad06f25a8' => __DIR__ . '/..' . '/symfony/deprecation-contracts/function.php',
        '0e6d7bf4a5811bfa5cf40c5ccd6fae6a' => __DIR__ . '/..' . '/symfony/polyfill-mbstring/bootstrap.php',
        '37a3dc5111fe8f707ab4c132ef1dbc62' => __DIR__ . '/..' . '/guzzlehttp/guzzle/src/functions_include.php',
        '603ce470d3b0980801c7a6185a3d6d53' => __DIR__ . '/..' . '/icanboogie/inflector/lib/helpers.php',
        '695627a778ea57b3dbaa8b8f96b4a849' => __DIR__ . '/..' . '/masroore/stopwords/src/helpers.php',
    );

    public static $prefixLengthsPsr4 = array (
        'v' => 
        array (
            'voku\\' => 5,
        ),
        'a' => 
        array (
            'andreskrey\\Readability\\' => 23,
        ),
        'W' => 
        array (
            'Webmozart\\Assert\\' => 17,
        ),
        'S' => 
        array (
            'Symfony\\Polyfill\\Mbstring\\' => 26,
            'Symfony\\Contracts\\EventDispatcher\\' => 34,
            'Solarium\\' => 9,
        ),
        'P' => 
        array (
            'Psr\\Log\\' => 8,
            'Psr\\Http\\Message\\' => 17,
            'Psr\\Http\\Client\\' => 16,
            'Psr\\EventDispatcher\\' => 20,
        ),
        'L' => 
        array (
            'League\\Event\\' => 13,
            'LanguageDetector\\' => 17,
        ),
        'K' => 
        array (
            'Kaiju\\Stopwords\\' => 16,
        ),
        'J' => 
        array (
            'JsonMachine\\' => 12,
        ),
        'I' => 
        array (
            'ICanBoogie\\' => 11,
        ),
        'H' => 
        array (
            'Html2Text\\' => 10,
        ),
        'G' => 
        array (
            'GuzzleHttp\\Psr7\\' => 16,
            'GuzzleHttp\\Promise\\' => 19,
            'GuzzleHttp\\' => 11,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'voku\\' => 
        array (
            0 => __DIR__ . '/..' . '/voku/stop-words/src/voku',
        ),
        'andreskrey\\Readability\\' => 
        array (
            0 => __DIR__ . '/..' . '/andreskrey/readability.php/src',
        ),
        'Webmozart\\Assert\\' => 
        array (
            0 => __DIR__ . '/..' . '/webmozart/assert/src',
        ),
        'Symfony\\Polyfill\\Mbstring\\' => 
        array (
            0 => __DIR__ . '/..' . '/symfony/polyfill-mbstring',
        ),
        'Symfony\\Contracts\\EventDispatcher\\' => 
        array (
            0 => __DIR__ . '/..' . '/symfony/event-dispatcher-contracts',
        ),
        'Solarium\\' => 
        array (
            0 => __DIR__ . '/..' . '/solarium/solarium/src',
        ),
        'Psr\\Log\\' => 
        array (
            0 => __DIR__ . '/..' . '/psr/log/Psr/Log',
        ),
        'Psr\\Http\\Message\\' => 
        array (
            0 => __DIR__ . '/..' . '/psr/http-factory/src',
            1 => __DIR__ . '/..' . '/psr/http-message/src',
        ),
        'Psr\\Http\\Client\\' => 
        array (
            0 => __DIR__ . '/..' . '/psr/http-client/src',
        ),
        'Psr\\EventDispatcher\\' => 
        array (
            0 => __DIR__ . '/..' . '/psr/event-dispatcher/src',
        ),
        'League\\Event\\' => 
        array (
            0 => __DIR__ . '/..' . '/league/event/src',
        ),
        'LanguageDetector\\' => 
        array (
            0 => __DIR__ . '/..' . '/landrok/language-detector/src/LanguageDetector',
        ),
        'Kaiju\\Stopwords\\' => 
        array (
            0 => __DIR__ . '/..' . '/masroore/stopwords/src',
        ),
        'JsonMachine\\' => 
        array (
            0 => __DIR__ . '/..' . '/halaxa/json-machine/src',
        ),
        'ICanBoogie\\' => 
        array (
            0 => __DIR__ . '/..' . '/icanboogie/inflector/lib',
        ),
        'Html2Text\\' => 
        array (
            0 => __DIR__ . '/..' . '/html2text/html2text/src',
            1 => __DIR__ . '/..' . '/html2text/html2text/test',
        ),
        'GuzzleHttp\\Psr7\\' => 
        array (
            0 => __DIR__ . '/..' . '/guzzlehttp/psr7/src',
        ),
        'GuzzleHttp\\Promise\\' => 
        array (
            0 => __DIR__ . '/..' . '/guzzlehttp/promises/src',
        ),
        'GuzzleHttp\\' => 
        array (
            0 => __DIR__ . '/..' . '/guzzlehttp/guzzle/src',
        ),
    );

    public static $prefixesPsr0 = array (
        'T' => 
        array (
            'Text' => 
            array (
                0 => __DIR__ . '/..' . '/pear/text_languagedetect',
            ),
        ),
        'S' => 
        array (
            'Smalot\\PdfParser\\' => 
            array (
                0 => __DIR__ . '/..' . '/smalot/pdfparser/src',
            ),
        ),
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit83cc9a19dfdab90b1f6bd762de78b87e::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit83cc9a19dfdab90b1f6bd762de78b87e::$prefixDirsPsr4;
            $loader->prefixesPsr0 = ComposerStaticInit83cc9a19dfdab90b1f6bd762de78b87e::$prefixesPsr0;
            $loader->classMap = ComposerStaticInit83cc9a19dfdab90b1f6bd762de78b87e::$classMap;

        }, null, ClassLoader::class);
    }
}
