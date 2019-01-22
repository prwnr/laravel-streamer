<?php

namespace Prwnr\Streamer\Redis;

/**
 * Trait Mergeable.
 */
trait Mergeable
{
    /**
     * Associative array dimensions to merge.
     *
     * @var int
     */
    protected $dimensions = 1;

    /**
     * @param array $data
     * @param int   $currentDimension
     *
     * @return array
     */
    public function toAssoc(array &$data, int $currentDimension = 1): array
    {
        $response = [];
        $key = 0;
        while ($data) {
            if ($this->dimensions > 1 && \is_array($data[$key])) {
                $currentDimension++;
                $response[$key] = $this->toAssoc($data[$key], $currentDimension);
                unset($data[$key]);
                $key++;
                continue;
            }

            if ($this->dimensions > 1 && !\is_array($data[$key]) && \is_array($data[$key + 1])) {
                $currentDimension++;
                $response[$data[$key]] = $this->toAssoc($data[$key + 1], $currentDimension);
                unset($data[$key], $data[$key + 1]);
                $key += 2;
                continue;
            }

            $response[$data[$key]] = $data[$key + 1];
            unset($data[$key], $data[$key + 1]);
            $key += 2;
        }

        return $response;
    }
}
