<?php

namespace Syntexa\Inspector;

class Profiler
{
    private static ?InspectorModule $inspector = null;
    private static array $spans = [];

    public static function setInspector(InspectorModule $inspector): void
    {
        self::$inspector = $inspector;
    }

    public static function start(string $label, array $metadata = []): string
    {
        $id = uniqid('span_', true);
        self::$spans[$id] = [
            'label' => $label,
            'start' => microtime(true),
            'metadata' => $metadata
        ];
        return $id;
    }

    public static function stop(string $id, array $additionalMetadata = []): void
    {
        if (!isset(self::$spans[$id])) {
            return;
        }

        $span = self::$spans[$id];
        $duration = (microtime(true) - $span['start']) * 1000;
        
        if (self::$inspector) {
            self::$inspector->addSegment('profile_span', [
                'label' => $span['label'],
                'duration' => round($duration, 3),
                'metadata' => array_merge($span['metadata'], $additionalMetadata)
            ]);
        }

        unset(self::$spans[$id]);
    }

    /**
     * Measure a callback
     */
    public static function measure(string $label, callable $callback, array $metadata = [])
    {
        $id = self::start($label, $metadata);
        try {
            return $callback();
        } finally {
            self::stop($id);
        }
    }
}
