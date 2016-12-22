<?php

namespace ContinuousPipe\River\CodeRepository\BitBucket;

use ContinuousPipe\River\AbstractCodeRepository;
use JMS\Serializer\Annotation as JMS;

class BitBucketCodeRepository extends AbstractCodeRepository
{
    /**
     * @JMS\Type("string")
     *
     * @var string
     */
    private $owner;

    /**
     * @JMS\Type("string")
     *
     * @var string
     */
    private $name;

    /**
     * @JMS\Type("string")
     *
     * @var string
     */
    private $address;

    /**
     * @JMS\Type("string")
     *
     * @var string
     */
    private $defaultBranch;

    /**
     * @JMS\Type("boolean")
     *
     * @var bool
     */
    private $private;

    public function __construct(string $identifier, string $owner, string $name, string $address, string $defaultBranch, bool $private)
    {
        parent::__construct($identifier);

        $this->name = $name;
        $this->owner = $owner;
        $this->address = $address;
        $this->defaultBranch = $defaultBranch;
        $this->private = $private;
    }

    /**
     * {@inheritdoc}
     */
    public function getDefaultBranch()
    {
        return $this->defaultBranch;
    }

    /**
     * {@inheritdoc}
     */
    public function getAddress()
    {
        return $this->address;
    }

    /**
     * {@inheritdoc}
     */
    public function getType()
    {
        return 'bitbucket';
    }

    /**
     * @return string
     */
    public function getOwner(): string
    {
        return $this->owner;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return bool
     */
    public function isPrivate(): bool
    {
        return $this->private;
    }
}
