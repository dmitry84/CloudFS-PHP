<?php

namespace CloudFS;


class Photo extends File {

    /**
     * Initializes a new instance of Photo.
     *
     * @param array $data The item data.
     * @param string $parentPath The item parent path.
     * @param \CloudFS\RESTAdapter $restAdapter The rest adapter instance.
     * @param array $parentState The parent state.
     */
    protected function __construct($data, $parentPath, $restAdapter, $parentState) {
        parent::__construct($data, $parentPath, $restAdapter, $parentState);
    }

}