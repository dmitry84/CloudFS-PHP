<?php

namespace CloudFS;


class Video extends File {

    /**
     * Initializes a new instance of Video.
     *
     * @param array $data The item data.
     * @param string $parentPath The item parent path.
     * @param \CloudFS\Filesystem $filesystem The file system instance.
     */
    protected function __construct($data, $parentPath, $filesystem) {
        parent::__construct($data, $parentPath, $filesystem);
    }

}