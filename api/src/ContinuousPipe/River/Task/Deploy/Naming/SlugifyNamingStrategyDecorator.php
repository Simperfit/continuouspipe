<?php

namespace ContinuousPipe\River\Task\Deploy\Naming;

use Cocur\Slugify\Slugify;
use ContinuousPipe\Model\Environment;
use Ramsey\Uuid\Uuid;

class SlugifyNamingStrategyDecorator implements EnvironmentNamingStrategy
{
    /**
     * @var EnvironmentNamingStrategy
     */
    private $decoratedStrategy;

    /**
     * @param EnvironmentNamingStrategy $decoratedStrategy
     */
    public function __construct(EnvironmentNamingStrategy $decoratedStrategy)
    {
        $this->decoratedStrategy = $decoratedStrategy;
    }

    /**
     * {@inheritdoc}
     */
    public function getName(Uuid $tideUuid, $expression = null)
    {
        return (new Slugify())->slugify(
            $this->decoratedStrategy->getName($tideUuid, $expression)
        );
    }
}
