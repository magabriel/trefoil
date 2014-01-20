<?php
/*
 * This file is part of the trefoil application.
 *
 * (c) Miguel Angel Gabriel <magabriel@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Trefoil\Events;

/**
 * @codeCoverageIgnore
 */
final class TrefoilEvents
{
    /**
     * This event is the earliest time possible for Trefoil plugins to start execution.
     * It is launched by Trefoil responding an EasybookEvents::PRE_PUBLISH event and
     * after performing all its initialization tasks (basically, plugin itself into the
     * Easybook system).
     */
    const PRE_PUBLISH_AND_READY = 'trefoil.publish.start';
}
