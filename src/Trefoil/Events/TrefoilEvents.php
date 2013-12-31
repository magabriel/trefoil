<?php

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
    const PRE_PUBLISH_AND_READY   = 'trefoil.publish.start';
}
