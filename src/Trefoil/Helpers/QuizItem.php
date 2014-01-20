<?php
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
     * @var Array of options
     */
    protected $options = array();

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
     * @param string $heading
     */
    public function setHeading($heading)
    {
        $this->heading = $heading;
    }

    /**
     * @return string
     */
    public function getHeading()
    {
        return $this->heading;
    }

    /**
     * @param string $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * @return string
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param string $internalId
     */
    public function setInternalId($internalId)
    {
        $this->internalId = $internalId;
    }

    /**
     * @return string
     */
    public function getInternalId()
    {
        return $this->internalId;
    }

    /**
     * @param string $introduction
     */
    public function setIntroduction($introduction)
    {
        $this->introduction = $introduction;
    }

    /**
     * @return string
     */
    public function getIntroduction()
    {
        return $this->introduction;
    }

    /**
     * @param Array $options
     */
    public function setOptions($options)
    {
        $this->options = $options;
    }

    /**
     * @return Array
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * @param string $subHeading
     */
    public function setSubHeading($subHeading)
    {
        $this->subHeading = $subHeading;
    }

    /**
     * @return string
     */
    public function getSubHeading()
    {
        return $this->subHeading;
    }

    /**
     * @param string $type
     */
    public function setType($type)
    {
        $this->type = $type;
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

}
