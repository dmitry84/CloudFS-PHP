<?php

namespace CloudFS;


class Document extends File {

    /**
     * Initializes a new instance of Document.
     *
     * @param array $data The item data.
     * @param string $parentPath The item parent path.
     * @param \CloudFS\RESTAdapter $restAdapter The rest adapter instance.
     */
    protected function __construct($data, $parentPath, $restAdapter) {
        parent::__construct($data, $parentPath, $restAdapter);
    }

}