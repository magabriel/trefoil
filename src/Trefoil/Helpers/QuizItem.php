<?php
declare(strict_types=1);
/*
 * This file is part of the trefoil application.
 *
 * (c) Miguel Angel Gabriel <magabriel@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Trefoil\Helpers;

/**
 * Base representation of a item in a Quiz.
 *
 */
abstract class QuizItem
{
    /**
     * Type of this quiz item (assigned by inheritors)
     *
     * @var string
     */
    protected $type;

    /**
     * Id (manually assigned)
     *
     * @var string
     */
    protected $id;

    /**
     * Internal Id
     *
     * @var string
     */
    protected $internalId;

    /**
     * Options
     *
     * @var array of options
     */
    protected $options = [];

    /**
     * The heading
     *
     * @var string
     */
    protected $heading;

    /**
     * The subheading
     *
     * @var string
     */
    protected $subHeading;

    /**
     * The introduction text
     *
     * @var string
     */
    protected $introduction;

    /**
     * An xref to the book item where the quiz item is defined.
     *
     * @var string
     */
    protected $xref;

    /**
     * @param string $heading
     */
    public function setHeading($heading): void
    {
        $this->heading = $heading;
    }

    /**
     * @return string
     */
    public function getHeading(): string
    {
        return $this->heading;
    }

    /**
     * @param string $id
     */
    public function setId($id): void
    {
        $this->id = $id;
    }

    /**
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @param string $internalId
     */
    public function setInternalId($internalId): void
    {
        $this->internalId = $internalId;
    }

    /**
     * @return string
     */
    public function getInternalId(): string
    {
        return $this->internalId;
    }

    /**
     * @param string $introduction
     */
    public function setIntroduction($introduction): void
    {
        $this->introduction = $introduction;
    }

    /**
     * @return string
     */
    public function getIntroduction(): ?string
    {
        return $this->introduction;
    }

    /**
     * @param array $options
     */
    public function setOptions($options): void
    {
        $this->options = $options;
    }

    /**
     * @return array
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * @param string $subHeading
     */
    public function setSubHeading($subHeading): void
    {
        $this->subHeading = $subHeading;
    }

    /**
     * @return string
     */
    public function getSubHeading(): ?string
    {
        return $this->subHeading;
    }

    /**
     * @param string $type
     */
    public function setType($type): void
    {
        $this->type = $type;
    }

    /**
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @param string $xref
     */
    public function setXref($xref): void
    {
        $this->xref = $xref;
    }

    /**
     * @return string
     */
    public function getXref(): string
    {
        return $this->xref;
    }
}
