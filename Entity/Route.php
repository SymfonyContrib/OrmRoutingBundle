<?php
namespace SymfonyContrib\Bundle\OrmRoutingBundle\Entity;

use Symfony\Cmf\Component\RoutingAuto\Model\AutoRouteInterface;
use Symfony\Component\Routing\Route as SymfonyRoute;
use Symfony\Cmf\Component\Routing\RouteObjectInterface;
use Symfony\Cmf\Component\Routing\RedirectRouteInterface;

/**
 * Doctrine ORM route.
 */
class Route extends SymfonyRoute implements RouteObjectInterface, RedirectRouteInterface, AutoRouteInterface
{
    const TYPE_DEFAULT  = 'D';
    const TYPE_AUTO     = 'A';
//    const TYPE_REDIRECT = 'R';

    protected $name;
    protected $position = 0;

    /** @var  string */
    protected $type;

    protected $destRouteName;
    protected $destRoute;

    /**
     * Unique id of this route.
     *
     * @var string
     */
    protected $id;

    /**
     * The referenced content object.
     *
     * @var object
     */
    protected $content;

    /**
     * Part of the URL that does not have parameters and thus can be used to
     * naivly guess candidate routes.
     *
     * Note that this field is not used by PHPCR-ODM
     *
     * @var string
     */
    protected $staticPrefix;

    /**
     * Variable pattern part. The static part of the pattern is the id without the prefix.
     *
     * @var string
     */
    protected $variablePattern;

    /**
     * Whether this route was changed since being last compiled.
     *
     * State information not persisted in storage.
     *
     * @var bool
     */
    protected $needRecompile = false;

    /**
     * Absolute uri to redirect to.
     */
    protected $uri;

    /**
     * The name of a target route (for use with standard symfony routes).
     */
    protected $routeName;

    /**
     * Target route document to redirect to different dynamic route.
     */
    protected $routeTarget;

    /**
     * Whether this is a permanent redirect. Defaults to false.
     */
    protected $permanent = false;

    /**
     * @var array
     */
    protected $parameters = [];

    protected $defaultKeyAutoRouteTag = '_auto_route_tag';

    /**
     * @var AutoRouteInterface
     */
    protected $redirectRoute;

    /**
     * Overwrite to be able to create route without pattern.
     *
     * Additional supported options are:
     *
     * * add_format_pattern: When set, ".{_format}" is appended to the route pattern.
     *                       Also implicitly sets a default/require on "_format" to "html".
     * * add_locale_pattern: When set, "/{_locale}" is prepended to the route pattern.
     *
     * @param array $options
     */
    public function __construct(array $options = [])
    {
        $this->type = self::TYPE_DEFAULT;
        $this->setDefaults([]);
        $this->setRequirements([]);
        $this->setOptions($options);

        if ($this->getOption('add_format_pattern')) {
            $this->setDefault('_format', 'html');
            $this->setRequirement('_format', 'html');
        }
    }

    public function __toString()
    {
        return $this->getName();
    }

    /**
     * Sets the name.
     *
     * @param string $name
     *
     * @return self
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Gets the name.
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Sets the position.
     *
     * @param int $position
     *
     * @return self
     */
    public function setPosition($position)
    {
        $this->position = $position;

        return $this;
    }

    /**
     * Gets the position.
     *
     * @return int
     */
    public function getPosition()
    {
        return $this->position;
    }

    /**
     * {@inheritdoc}
     */
    public function getRouteKey()
    {
        return $this->getId();
    }

    /**
     * Get the repository path of this url entry.
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return string the static prefix part of this route
     */
    public function getStaticPrefix()
    {
        return $this->staticPrefix;
    }

    /**
     * @param string $prefix The static prefix part of this route
     *
     * @return Route
     */
    public function setStaticPrefix($prefix)
    {
        $this->staticPrefix = $prefix;

        return $this;
    }

    /**
     * Set the object this url points to.
     *
     * @param mixed $object A content object that can be persisted by the storage layer.
     *
     * @return Route
     */
    public function setContent($object)
    {
        if (0) { // redirect
            throw new \LogicException('Do not set a content for the redirect route. It is its own content.');
        }

        $this->content = $object;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getContent()
    {
        if (0) { // redirect
            return $this;
        }

        return $this->content;
    }

    /**
     * {@inheritdoc}
     *
     * Prevent setting the default 'compiler_class' so that we do not persist it
     */
    public function setOptions(array $options)
    {
        return $this->addOptions($options);
    }

    /**
     * {@inheritdoc}
     *
     * Handling the missing default 'compiler_class'
     *
     * @see setOptions
     */
    public function getOption($name)
    {
        $option = parent::getOption($name);
        if (null === $option && 'compiler_class' === $name) {
            return 'Symfony\\Component\\Routing\\RouteCompiler';
        }
        if ($this->isBooleanOption($name)) {
            return (bool)$option;
        }

        return $option;
    }

    /**
     * {@inheritdoc}
     *
     * Handling the missing default 'compiler_class'
     *
     * @see setOptions
     */
    public function getOptions()
    {
        $options = parent::getOptions();
        if (!array_key_exists('compiler_class', $options)) {
            $options['compiler_class'] = 'Symfony\\Component\\Routing\\RouteCompiler';
        }
        foreach ($options as $key => $value) {
            if ($this->isBooleanOption($key)) {
                $options[$key] = (bool)$value;
            }
        }

        return $options;
    }

    /**
     * Helper method to check if an option is a boolean option to allow better forms.
     *
     * @param string $name
     *
     * @return bool
     */
    protected function isBooleanOption($name)
    {
        return in_array($name, ['add_format_pattern', 'add_locale_pattern']);
    }

    /**
     * {@inheritdoc}
     */
    public function getPath()
    {
        $pattern = '';
        if ($this->getOption('add_locale_pattern')) {
            $pattern .= '/{_locale}';
        }
        $pattern .= $this->getStaticPrefix();
        $pattern .= $this->getVariablePattern();
        if ($this->getOption('add_format_pattern') && !preg_match('/(.+)\.[a-z]+$/i', $pattern, $matches)) {
            $pattern .= '.{_format}';
        }

        return $pattern;
    }

    /**
     * {@inheritdoc}
     *
     * It is recommended to use setVariablePattern to just set the part after
     * the static part. If you use this method, it will ensure that the
     * static part is not changed and only change the variable part.
     *
     * When using PHPCR-ODM, make sure to persist the route before calling this
     * to have the id field initialized.
     */
    public function setPath($pattern)
    {
        $len = strlen($this->getStaticPrefix());

        if (strncmp($this->getStaticPrefix(), $pattern, $len)) {
            throw new \InvalidArgumentException('You can not set a pattern for the route that does not start with its current static prefix. First update the static prefix or directly use setVariablePattern.');
        }

        return $this->setVariablePattern(substr($pattern, $len));
    }

    /**
     * @return string the variable part of the url pattern
     */
    public function getVariablePattern()
    {
        return $this->variablePattern;
    }

    /**
     * @param string $variablePattern the variable part of the url pattern
     *
     * @return Route
     */
    public function setVariablePattern($variablePattern)
    {
        $this->variablePattern = $variablePattern;
        $this->needRecompile   = true;

        return $this;
    }

    /**
     * Set the route this redirection route points to. This must be a PHPCR-ODM
     * mapped object.
     *
     * @param SymfonyRoute $document the redirection target route
     */
    public function setRouteTarget(SymfonyRoute $document)
    {
        $this->routeTarget = $document;
    }

    /**
     * {@inheritdoc}
     */
    public function getRouteTarget()
    {
        return $this->routeTarget;
    }

    /**
     * Set a symfony route name for this redirection.
     *
     * @param string $routeName
     */
    public function setRouteName($routeName)
    {
        $this->routeName = $routeName;
    }

    /**
     * {@inheritdoc}
     */
    public function getRouteName()
    {
        return $this->routeName;
    }

    /**
     * Set whether this redirection should be permanent or not. Default is
     * false.
     *
     * @param bool $permanent if true this is a permanent redirection
     */
    public function setPermanent($permanent)
    {
        $this->permanent = $permanent;
    }

    /**
     * {@inheritdoc}
     */
    public function isPermanent()
    {
        return $this->permanent;
    }

    /**
     * Set the parameters for building this route. Used with both route name
     * and target route document.
     *
     * @param array $parameters a hashmap of key to value mapping for route parameters
     */
    public function setParameters(array $parameters)
    {
        $this->parameters = $parameters;
    }

    /**
     * {@inheritdoc}
     */
    public function getParameters()
    {
        return $this->parameters;
    }

    /**
     * Set the absolute redirection target URI.
     *
     * @param string $uri the absolute URI
     */
    public function setUri($uri)
    {
        $this->uri = $uri;
    }

    /**
     * {@inheritdoc}
     */
    public function getUri()
    {
        return $this->uri;
    }

    /**
     * {@inheritdoc}
     */
    public function setAutoRouteTag($autoRouteTag)
    {
        $this->setDefault($this->defaultKeyAutoRouteTag, $autoRouteTag);
    }

    /**
     * {@inheritdoc}
     */
    public function getAutoRouteTag()
    {
        return $this->getDefault($this->defaultKeyAutoRouteTag);
    }

    /**
     * {@inheritdoc}
     */
    public function setType($type)
    {
        $this->type = $type;
        $this->setDefault('type', $type);

        return $this;
    }

    public function getType()
    {
        return $this->type;
    }

    /**
     * {@inheritdoc}
     */
    public function setRedirectTarget($redirectRoute)
    {
        $this->redirectRoute = $redirectRoute;
    }

    /**
     * {@inheritdoc}
     */
    public function getRedirectTarget()
    {
        return $this->redirectRoute;
    }

    /**
     * {@inheritdoc}
     *
     * Overwritten to make sure the route is recompiled if the pattern was changed
     */
    public function compile()
    {
        if ($this->needRecompile) {
            // calling parent::setPath just to let it set compiled=null. the parent $path field is never used
            parent::setPath($this->getPath());
        }

        return parent::compile();
    }
}
