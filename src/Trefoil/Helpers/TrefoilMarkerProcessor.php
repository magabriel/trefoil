<?php

namespace Trefoil\Helpers;

/**
 * Class TrefoilMarkerProcessor.
 *
 * This class processes trefoil markers.
 *
 * A trefoil marker is a block containing the call to one of the trefoil
 * expanded syntax functions, like: '{@ my_function() @}'.
 *
 * The class will convert those blocks to Twig and execute them as if they
 * were writen with Twig syntax (so the above example will be converted
 * to '{{ my_function() }}' and executed.
 *
 * It is needed to be able to add custom block markers using Twig syntax
 * without interfering with the "Twig in content" functionality of
 * TwigExtensionPlugin.
 *
 * @package Trefoil\Helpers
 */
class TrefoilMarkerProcessor
{
    /**
     * @var string
     */
    protected $functionNames = [];

    /**
     * @var \Twig_Environment
     */
    protected $twig;

    public function __construct()
    {
        // we use a separate Twig environment for this
        $this->twig = new \Twig_Environment(new \Twig_Loader_String(),
            [
                'debug' => true,
                'cache' => false,
                'strict_variables' => true,
                'autoescape' => false
            ]
        );
    }

    /**
     * Register a function to be called from a trefoil marker.
     *
     * @param          $trefoilMarkerName
     * @param callable $code
     */
    public function registerMarker($trefoilMarkerName, callable $code)
    {
        $this->twig->addFunction(new \Twig_SimpleFunction($trefoilMarkerName, $code));
        $this->functionNames[] = $trefoilMarkerName;
    }

    /**
     * Parse a text to execute configured trefoil markers.
     *
     * @param $text string
     * @return string|null
     */
    public function parse($text)
    {
        // preserve all existing markdown code blocks to avoid being processed inside
        $preserver = new TextPreserver();
        $preserver->setText($text);
        $preserver->preserveMarkdowmCodeBlocks();
        $text = $preserver->getText();

        // capture all trefoil markers
        $regExp = '/';
        $regExp .= '(?<trefoilmarker>';
        $regExp .= '{@ +'; // block opening delimiter followed by one or more blanks
        $regExp .= '=*[ \n]*'; // optional visual delimiter: a series of "=" followed by optional blanks
        $regExp .= '(?<function>'; // begin function call
        $regExp .= '(' . join('|', $this->functionNames) . ')'; // one of the functions to process
        $regExp .= ' *\('; // zero or more blanks and opening parenthesis
        $regExp .= '.*(?=@})'; // rest of block content up until the closing (positive lookahead)
        $regExp .= ')'; // end function call
        $regExp .= '@}'; // block closing
        $regExp .= ')';
        $regExp .= '/Ums'; // Ungreedy, multiline, dotall

        $text = preg_replace_callback(
            $regExp,
            function ($matches) {
                $twigCall = '{{' . $matches['function'] . '}}';

                return $this->twig->render($twigCall);
            },
            $text
        );

        // restore the existing markdown code blocks
        $preserver->setText($text);
        $preserver->restore();
        $text = $preserver->getText();

        return $text;
    }
}