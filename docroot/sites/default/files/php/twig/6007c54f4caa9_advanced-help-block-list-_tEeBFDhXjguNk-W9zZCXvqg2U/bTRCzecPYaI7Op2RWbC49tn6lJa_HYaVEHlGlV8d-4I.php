<?php

use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Markup;
use Twig\Sandbox\SecurityError;
use Twig\Sandbox\SecurityNotAllowedTagError;
use Twig\Sandbox\SecurityNotAllowedFilterError;
use Twig\Sandbox\SecurityNotAllowedFunctionError;
use Twig\Source;
use Twig\Template;

/* profiles/contrib/openy/modules/custom/advanced_help_block/templates/advanced-help-block-list-render.html.twig */
class __TwigTemplate_30bb4ef188e93ceae79195f7298675da2837b4cabf8c093543ff2999bb8e1c92 extends \Twig\Template
{
    public function __construct(Environment $env)
    {
        parent::__construct($env);

        $this->parent = false;

        $this->blocks = [
        ];
        $this->sandbox = $this->env->getExtension('\Twig\Extension\SandboxExtension');
        $tags = ["if" => 17, "for" => 21];
        $filters = ["escape" => 16, "length" => 18, "t" => 26];
        $functions = ["attach_library" => 16];

        try {
            $this->sandbox->checkSecurity(
                ['if', 'for'],
                ['escape', 'length', 't'],
                ['attach_library']
            );
        } catch (SecurityError $e) {
            $e->setSourceContext($this->getSourceContext());

            if ($e instanceof SecurityNotAllowedTagError && isset($tags[$e->getTagName()])) {
                $e->setTemplateLine($tags[$e->getTagName()]);
            } elseif ($e instanceof SecurityNotAllowedFilterError && isset($filters[$e->getFilterName()])) {
                $e->setTemplateLine($filters[$e->getFilterName()]);
            } elseif ($e instanceof SecurityNotAllowedFunctionError && isset($functions[$e->getFunctionName()])) {
                $e->setTemplateLine($functions[$e->getFunctionName()]);
            }

            throw $e;
        }

    }

    protected function doDisplay(array $context, array $blocks = [])
    {
        // line 15
        echo "
";
        // line 16
        echo $this->env->getExtension('Drupal\Core\Template\TwigExtension')->escapeFilter($this->env, $this->env->getExtension('Drupal\Core\Template\TwigExtension')->attachLibrary("advanced_help_block/ahb_modal"), "html", null, true);
        echo "
";
        // line 17
        if ((isset($context["render_type"]) || array_key_exists("render_type", $context))) {
            // line 18
            echo "    ";
            if (((isset($context["blocks"]) || array_key_exists("blocks", $context)) && (twig_length_filter($this->env, ($context["blocks"] ?? null)) > 0))) {
                // line 19
                echo "        <div id=\"block-help\" role=\"complementary\" class=\"block block-help block-help-block\">
            ";
                // line 20
                if ((($context["render_type"] ?? null) == "block")) {
                    // line 21
                    echo "                ";
                    $context['_parent'] = $context;
                    $context['_seq'] = twig_ensure_traversable(($context["blocks"] ?? null));
                    foreach ($context['_seq'] as $context["i"] => $context["block"]) {
                        // line 22
                        echo "                    <div class=\"block-help-item block-help-item-";
                        echo $this->env->getExtension('Drupal\Core\Template\TwigExtension')->escapeFilter($this->env, $this->sandbox->ensureToStringAllowed($context["i"]), "html", null, true);
                        echo " messages messages--info hidden\" data-block-id=\"";
                        echo $this->env->getExtension('Drupal\Core\Template\TwigExtension')->escapeFilter($this->env, $this->sandbox->ensureToStringAllowed($context["i"]), "html", null, true);
                        echo "\">
                        ";
                        // line 23
                        if ($this->getAttribute($context["block"], "title", [], "any", true, true)) {
                            // line 24
                            echo "                            <strong>";
                            echo $this->env->getExtension('Drupal\Core\Template\TwigExtension')->escapeFilter($this->env, $this->sandbox->ensureToStringAllowed($this->getAttribute($context["block"], "title", [])), "html", null, true);
                            echo "</strong>
                        ";
                        }
                        // line 26
                        echo "                        <a href=\"#\" class=\"help-more\">";
                        echo $this->env->getExtension('Drupal\Core\Template\TwigExtension')->renderVar(t("Show more"));
                        echo "</a>
                        <a href=\"#\" class=\"help-close\">×</a>
                        <div class=\"help-content hidden\">
                            ";
                        // line 29
                        if ($this->getAttribute($context["block"], "description", [], "any", true, true)) {
                            // line 30
                            echo "                                ";
                            echo $this->env->getExtension('Drupal\Core\Template\TwigExtension')->escapeFilter($this->env, $this->sandbox->ensureToStringAllowed($this->getAttribute($context["block"], "description", [])), "html", null, true);
                            echo "
                            ";
                        }
                        // line 32
                        echo "                            ";
                        if ($this->getAttribute($context["block"], "youtube_link", [], "any", true, true)) {
                            // line 33
                            echo "                                <p>";
                            echo $this->env->getExtension('Drupal\Core\Template\TwigExtension')->escapeFilter($this->env, $this->sandbox->ensureToStringAllowed($this->getAttribute($context["block"], "youtube_link", [])), "html", null, true);
                            echo "</p>
                            ";
                        }
                        // line 35
                        echo "                        </div>
                    </div>
                ";
                    }
                    $_parent = $context['_parent'];
                    unset($context['_seq'], $context['_iterated'], $context['i'], $context['block'], $context['_parent'], $context['loop']);
                    $context = array_intersect_key($context, $_parent) + $_parent;
                    // line 38
                    echo "            ";
                }
                // line 39
                echo "        </div>
    ";
            }
        }
    }

    public function getTemplateName()
    {
        return "profiles/contrib/openy/modules/custom/advanced_help_block/templates/advanced-help-block-list-render.html.twig";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  127 => 39,  124 => 38,  116 => 35,  110 => 33,  107 => 32,  101 => 30,  99 => 29,  92 => 26,  86 => 24,  84 => 23,  77 => 22,  72 => 21,  70 => 20,  67 => 19,  64 => 18,  62 => 17,  58 => 16,  55 => 15,);
    }

    /** @deprecated since 1.27 (to be removed in 2.0). Use getSourceContext() instead */
    public function getSource()
    {
        @trigger_error('The '.__METHOD__.' method is deprecated since version 1.27 and will be removed in 2.0. Use getSourceContext() instead.', E_USER_DEPRECATED);

        return $this->getSourceContext()->getCode();
    }

    public function getSourceContext()
    {
        return new Source("", "profiles/contrib/openy/modules/custom/advanced_help_block/templates/advanced-help-block-list-render.html.twig", "/var/www/html/docroot/profiles/contrib/openy/modules/custom/advanced_help_block/templates/advanced-help-block-list-render.html.twig");
    }
}
