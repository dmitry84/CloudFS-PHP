<?php

namespace CloudFS;

/**
 * Bitcasa Client PHP SDK
 * Copyright (C) 2014 Bitcasa, Inc.
 *
 * This file contains an SDK in PHP for accessing the Bitcasa infinite drive.
 *
 * For support, please send email to support@bitcasa.com.
 */

use CloudFS\Exception\InvalidArgumentException;
use CloudFS\Filesystem;
use CloudFS\Utils\BitcasaConstants;
use CloudFS\BitcasaUtils;
use CloudFS\HTTPConnector;
use CloudFS\Utils\Conflict;
use CloudFS\Utils\Exists;
use CloudFS\Utils\Assert;
use CloudFS\Utils\RestoreMethod;


class RESTAdapter {

    private $credential;
    private $accessToken;
    private $debug;

    /**
     * Initializes the bitcasa api instance.
     *
     * @param Credential $credential
     */
    public function __construct($credential) {
        $this->accessToken = null;
        $this->credential = $credential;
        $this->debug = getenv("BC_DEBUG") != null;
    }

    /**
     * Authenticates with bitcasa and gets the access token.
     *
     * @param Session $session The bitcasa session.
     * @param string $username Bitcasa username.
     * @param string $password Bitcasa password.
     * @return The success status of retrieving the access token.
     */
    public function authenticate($session, $username, $password) {

        Assert::assertStringOrEmpty($username,2);
        Assert::assertStringOrEmpty($password,3);

        $now = time();
        $connection = new HTTPConnector($session);
        $this->accessToken = null;

        $date = strftime(BitcasaConstants::DATE_FORMAT, $now);
        $bodyParams = array();

        $bodyParams[BitcasaConstants::PARAM_GRANT_TYPE] = urlencode(BitcasaConstants::PARAM_PASSWORD);
        $bodyParams[BitcasaConstants::PARAM_PASSWORD] = urlencode($password);
        $bodyParams[BitcasaConstants::PARAM_USERNAME] = urlencode($username);

        $parameters = BitcasaUtils::generateParamsString($bodyParams);
        $url = BitcasaUtils::getRequestUrl($this->credential, BitcasaConstants::METHOD_OAUTH2, BitcasaConstants::METHOD_TOKEN, null);
        //generate authorization value
        $uri = BitcasaConstants::API_VERSION_2 . BitcasaConstants::METHOD_OAUTH2 . BitcasaConstants::METHOD_TOKEN;
        $authorizationValue = bitcasaUtils::generateAuthorizationValue($session, $uri, $parameters, $date);

        $connection->addHeader(BitcasaConstants::HEADER_CONTENT_TYPE, BitcasaConstants::FORM_URLENCODED);
        $connection->AddHeader(BitcasaConstants::HEADER_DATE, $date);
        $connection->AddHeader(BitcasaConstants::HEADER_AUTORIZATION, $authorizationValue);

        $connection->setData($parameters);
        $connection->post($url);
        $resp = $connection->getResponse(true, false);

        if (isset($resp["access_token"])) {
            $this->credential->setAccessToken($resp["access_token"]);
        }

        if (isset($resp["token_type"])) {
            $this->credential->setTokenType($resp["token_type"]);
        }

        if ($this->debug) {
            var_dump($resp);
        }

        return $resp;
    }

    /**
     * Retrieves the item list if a prent item is given, else returns the list
     * of items under root.
     *
     * @param string $parent The parent for which the items should be retrieved for.
     * @param int $version Version filter for items being retrieved.
     * @param int $depth Depth variable for how many levels of items to be retrieved.
     * @param mixed $filter Variable to filter the items being retrieved.
     * @return The item list.
     * @throws Exception
     */
    public function getList($parent = null, $version = 0, $depth = 0, $filter = null) {
        $params = array();
        $endpoint = BitcasaConstants::METHOD_FOLDERS;

        if ($parent == null) {
            $endpoint .= "/";
        } else if (!is_string($parent)) {
            throw new Exception("Invalid parent path");
        } else {
            $endpoint .= $parent;
        }

        if ($version > 0) {
            $params[BitcasaConstants::PARAM_VERSION] = $version;
        }
        if ($depth > 0) {
            $params[BitcasaConstants::PARAM_DEPTH] = $depth;
        }
        if ($filter != null) {
            $params[BitcasaConstants::PARAM_FILTER] = $filter;
        }

        $connection = new HTTPConnector($this->credential->getSession());
        $url = $this->credential->getRequestUrl($endpoint, null, $params);

        $connection->get($url);
        return $connection->getResponse(true);
    }

    /**
     * Retrieves the meta data of a item at a given path.
     *
     * @param string $path The path of the item.
     * @return The json string containing the meta data of the item.
     * @throws Exception
     */
    public function getItemMeta($path) {
        $params = array();
        $endpoint = BitcasaConstants::METHOD_ITEMS;

        if ($path == null) {
            $endpoint .= "/";
        } else if (!is_string($path)) {
            throw new Exception("Invalid parent path");
        } else {
            $endpoint .= $path;
        }
        if (substr($endpoint, -1) != "/") {
            $endpoint .= "/";
        }
        $endpoint .= "meta";

        $connection = new HTTPConnector($this->credential->getSession());
        $url = $this->credential->getRequestUrl($endpoint, null, $params);
        $connection->get($url);

        return $connection->getResponse(true);
    }


    /**
     * Retrieves the meta data of a file at a given path.
     *
     * @param string $path The path of the item.
     * @return The meta data of the item.
     * @throws Exception
     */
    public function getFileMeta($path) {
        $params = array();
        $endpoint = BitcasaConstants::METHOD_FILES;

        if ($path == null) {
            throw new Exception("Path variable not supplied. Root is not of type file.");
        } else if (!is_string($path)) {
            throw new Exception("Invalid parent path");
        } else {
            $endpoint .= $path;
        }
        if (substr($endpoint, -1) != "/") {
            $endpoint .= '/';
        }
        $endpoint .= "meta";

        $connection = new HTTPConnector($this->credential->getSession());
        $url = $this->credential->getRequestUrl($endpoint, null, $params);

        $connection->get($url);
        return $connection->getResponse(true);
    }

    /**
     * Retrieves the meta data of a folder at a given path.
     *
     * @param string $path The path of the item.
     * @return The meta data of the item.
     * @throws Exception
     */
    public function getFolderMeta($path) {
        $params = array();
        $endpoint = BitcasaConstants::METHOD_FOLDERS;

        if ($path == null) {
            $endpoint .= "/";
        } else if (!is_string($path)) {
            throw new Exception("Invalid parent path");
        } else {
            $endpoint .= $path;
        }
        if (substr($endpoint, -1) != "/") {
            $endpoint .= "/";
        }
        $endpoint .= 'meta';

        $connection = new HTTPConnector($this->credential->getSession());
        $url = $this->credential->getRequestUrl($endpoint, null, $params);

        $connection->get($url);
        return $connection->getResponse(true);
    }

    /**
     * Create a folder at a given path with the supplied name.
     *
     * @param string $parentPath The folder path under which the new folder should be created.
     * @param string $name The name for the folder to be created.
     * @param string $exists Specifies the action to take if the folder already exists.
     * @return An instance of the newly created item of type Folder.
     * @throws InvalidArgument
     */
    public function createFolder($parentPath, $name, $exists = Exists::FAIL) {
        $item = null;
        $connection = new HTTPConnector($this->credential->getSession());
        if ($parentPath == null) {
            $parentPath = "/";
        }

        Assert::assertPath($parentPath, 1);
        Assert::assertStringOrEmpty($name, 2);

        $url = $this->credential->getRequestUrl(BitcasaConstants::METHOD_FOLDERS, $parentPath,
            array(BitcasaConstants::PARAM_OPERATION => BitcasaConstants::OPERATION_CREATE));
        $body = BitcasaUtils::generateParamsString(array("name" => $name, "exists" => $exists));

        $connection->setData($body);
        if ($this->debug) {
            var_dump($url);
        }

        $connection->post($url);
        return $connection->getResponse(true);
    }

    /**
     * Delete a folder from cloud storage.
     *
     * @param string $path The path of the folder to be deleted.
     * @param bool $commit Either move the folder to the Trash (false) or delete it immediately (true)
     * @param bool $force The flag to force delete the folder from cloud storage.
     * @return The success/fail response of the delete operation.
     */
    public function deleteFolder($path, $commit = false, $force = false) {
        Assert::assertStringOrEmpty($path, 1);
        $connection = new HTTPConnector($this->credential->getSession());
        $force_option = array();

        if ($force == true) {
            $force_option['force'] = 'true';
        }

        if ($commit == true) {
            $force_option['commit'] = 'true';
        }

        $url = $this->credential->getRequestUrl(BitcasaConstants::METHOD_FOLDERS, $path, $force_option);

        $connection->delete($url);
        $response = $connection->getResponse(true);
        return $response['result']['success'];
    }

    /**
     * Delete a file from cloud storage.
     *
     * @param string $path The path of the file to be deleted.
     * @param bool $force The flag to force delete the file from cloud storage.
     * @return The success/fail response of the delete operation.
     */
    public function deleteFile($path, $force = false) {
        Assert::assertStringOrEmpty($path, 1);
        $connection = new HTTPConnector($this->credential->getSession());
        $force_option = array();
        if ($force == true) {
            $force_option["force"] = "true";
        }

        $url = $this->credential->getRequestUrl(BitcasaConstants::METHOD_FILES, $path, $force_option);

        $connection->delete($url);
        $response = $connection->getResponse(true);

        return $response['result']['success'];
    }

    /**
     * Alter the attributes of the folder at a given path.
     *
     * @param string $path The folder path.
     * @param mixed $values The attributes to be altered.
     * @param string $conflict Specifies the action to take if a conflict occurs.
     * @return The success/fail response of the alter operation.
     * @throws InvalidArgument
     */
    public function alterFolderMeta($path, $values, $conflict = Conflict::FAIL) {
        Assert::assertStringOrEmpty($path, 1);
        $connection = new HTTPConnector($this->credential->getSession());
        $url = $this->credential->getRequestUrl(BitcasaConstants::METHOD_FOLDERS, $path . "/meta", array());
        $values['version-conflict'] = $conflict;
        $body = BitcasaUtils::generateParamsString($values);

        $connection->setData($body);
        $connection->post($url);
        return $connection->getResponse(true);
    }

    /**
     * Alter the attributes of the file at a given path.
     *
     * @param string $path The file path.
     * @param mixed $values The attributes to be altered.
     * @param string $conflict Specifies the action to take if a conflict occurs.
     * @return The success/fail response of the alter operation.
     * @throws InvalidArgument
     */
    public function alterFileMeta($path, $values, $conflict = Conflict::FAIL) {
        Assert::assertStringOrEmpty($path, 1);
        $connection = new HTTPConnector($this->credential->getSession());
        $url = $this->credential->getRequestUrl(BitcasaConstants::METHOD_FILES, $path . "/meta", array());
        $values['version-conflict'] = $conflict;
        $body = BitcasaUtils::generateParamsString($values);

        $connection->setData($body);
        $connection->post($url);
        return $connection->getResponse(true);
    }

    /**
     * Copy a folder at a given path to a specified destination.
     *
     * @param string $path The path of the folder to be copied.
     * @param string $destination Path to which the folder should be copied to.
     * @param string $name Name of the newly copied folder.
     * @param string $exists Specifies the action to take if the folder already exists.
     * @return The copied folder instance.
     */
    public function copyFolder($path, $destination, $name = null, $exists = Exists::FAIL) {
        Assert::assertStringOrEmpty($path, 1);
        Assert::assertString($destination, 2);
        $item = null;
        $connection = new HTTPConnector($this->credential->getSession());
        $url = $this->credential->getRequestUrl(BitcasaConstants::METHOD_FOLDERS, $path,
            array(BitcasaConstants::PARAM_OPERATION => BitcasaConstants::OPERATION_COPY));
        $params = array("to" => $destination, "exists" => $exists);
        if ($name != null) {
            $params['name'] = $name;
        }

        $body = BitcasaUtils::generateParamsString($params);

        $connection->setData($body);
        $connection->post($url);
        return $connection->getResponse(true);
    }

    /**
     * Copy a file at a given path to a specified destination.
     *
     * @param string $path The path of the file to be copied.
     * @param string $destination Path to which the file should be copied to.
     * @param string $name Name of the newly copied file.
     * @param string $exists Specifies the action to take if the file already exists.
     * @return The copied file instance.
     */
    public function copyFile($path, $destination, $name = null, $exists = Exists::FAIL) {
        Assert::assertStringOrEmpty($path, 1);
        Assert::assertString($destination, 2);
        $item = null;
        $connection = new HTTPConnector($this->credential->getSession());
        $url = $this->credential->getRequestUrl(BitcasaConstants::METHOD_FILES, $path,
            array(BitcasaConstants::PARAM_OPERATION => BitcasaConstants::OPERATION_COPY));

        $params = array("to" => $destination, "exists" => $exists);
        if ($name != null) {
            $params['name'] = $name;
        }
        $body = BitcasaUtils::generateParamsString($params);

        $connection->setData($body);
        $connection->post($url);
        return $connection->getResponse(true);
    }

    /**
     * Move a folder at a given path to a specified destination.
     *
     * @param string $path The path of the folder to be moved.
     * @param string $destination Path to which the folder should be moved to.
     * @param string $name Name of the newly moved folder.
     * @param string $exists Specifies the action to take if the folder already exists.
     * @return The json response containing moved folder data.
     */
    public function moveFolder($path, $destination, $name = null, $exists = Exists::FAIL) {
        Assert::assertStringOrEmpty($path, 1);
        Assert::assertPath($path, 1);
        Assert::assertPath($destination, 2);
        $item = null;
        $connection = new HTTPConnector($this->credential->getSession());
        $url = $this->credential->getRequestUrl(BitcasaConstants::METHOD_FOLDERS, $path,
            array(BitcasaConstants::PARAM_OPERATION => BitcasaConstants::OPERATION_MOVE));
        $params = array("to" => $destination, "exists" => $exists);
        if ($name != null) {
            $params['name'] = $name;
        }
        $body = BitcasaUtils::generateParamsString($params);

        $connection->setData($body);
        $connection->post($url);
        return $connection->getResponse(true);
    }

    /**
     * Move a file at a given path to a specified destination.
     *
     * @param string $path The path of the file to be moved.
     * @param string $destination Path to which the file should be moved to.
     * @param string $name Name of the newly moved file.
     * @param string $exists Specifies the action to take if the file already exists.
     * @return The json response containing moved file data.
     */
    public function moveFile($path, $destination, $name = null, $exists = Exists::FAIL) {
        Assert::assertStringOrEmpty($path, 1);
        Assert::assertPath($path, 1);
        Assert::assertPath($destination, 2);
        $item = null;
        $connection = new HTTPConnector($this->credential->getSession());
        $url = $this->credential->getRequestUrl(BitcasaConstants::METHOD_FILES, $path,
            array(BitcasaConstants::PARAM_OPERATION => BitcasaConstants::OPERATION_MOVE));
        $params = array("to" => $destination, "exists" => $exists);
        if ($name != null) {
            $params['name'] = $name;
        }
        $body = BitcasaUtils::generateParamsString($params);

        $connection->setData($body);
        $connection->post($url);
        return $connection->getResponse(true);
    }

    /**
     * Download a file from the cloud storage.
     *
     * @param string $path Path of the file to be downloaded.
     * @param string $localDestinationPath The local path of the file to download the content.
     * @param mixed $downloadProgressCallback The download progress callback function. This function should take
     * 'downloadSize', 'downloadedSize', 'uploadSize', 'uploadedSize' as arguments.
     * @return The download status.
     */
    public function downloadFile($path, $localDestinationPath, $downloadProgressCallback) {
        $connection = new HTTPConnector($this->credential->getSession());
        $url = $this->credential->getRequestUrl(BitcasaConstants::METHOD_FILES, $path);
        return $connection->download($url, $localDestinationPath, $downloadProgressCallback);
    }

    /**
     * Upload a file on to the given path.
     *
     * @param string $parentPath The parent folder path to which the file is to be uploaded.
     * @param string $name The upload file name.
     * @param string $filePath The file path for the file to be downloaded.
     * @param string $exists The action to take if the item already exists.
     * @param mixed $uploadProgressCallback The upload progress callback function. This function should take
     * 'downloadSize', 'downloadedSize', 'uploadSize', 'uploadedSize' as arguments.
     * @return An instance of the uploaded item.
     */
    public function uploadFile($parentPath, $name, $filePath, $exists = Exists::OVERWRITE, $uploadProgressCallback = null) {
        Assert::assertStringOrNull($parentPath,1);
        Assert::assertStringOrEmpty($name,2);
        Assert::assertStringOrEmpty($filePath,3);

        if($parentPath == null){
            $parentPath = '/';
        }

        $params = array();
        $connection = new HTTPConnector($this->credential->getSession());
        $connection->raw();
        $url = $this->credential->getRequestUrl(BitcasaConstants::METHOD_FILES, $parentPath, $params);
        $connection->postMultipart($url, $name, $filePath, $exists, $uploadProgressCallback);
        return $connection->getResponse(true);
    }

    /**
     * Restores the file at a given path to a given destination.
     *
     * @param string $path The item path.
     * @param string $destination The destination path.
     * @param string $restoreMethod The restore method.
     * @param string $restoreArgument The restore argument.
     * @return The state of the restore operation.
     */
    public function restore($path, $destination, $restoreMethod = RestoreMethod::FAIL, $restoreArgument = null) {
        Assert::assertStringOrEmpty($path, 1);
        Assert::assertStringOrEmpty($destination, 2);
        $connection = new HTTPConnector($this->credential->getSession());
        $params = array();
        $status = false;

        if ($restoreMethod == RestoreMethod::RECREATE) {
            $params['recreate-path'] = $destination;
            $params['restore'] = RestoreMethod::RECREATE;

        } elseif ($restoreMethod == RestoreMethod::FAIL) {
            $params['restore'] = RestoreMethod::FAIL;

        } elseif ($restoreMethod == RestoreMethod::RESCUE) {
            if ($destination != null) {
                $params['rescue-path'] = $destination;
            }
            $params['restore'] = RestoreMethod::RESCUE;
        }
        $body = $params;
        $url = $this->credential->getRequestUrl(BitcasaConstants::METHOD_TRASH, '/' . $path,
            array());
        $body = BitcasaUtils::generateParamsString($body);

        $connection->setData($body);
        $connection->post($url);
        $response = $connection->getResponse(true);

        if (!empty($response) && !empty($response['result'])) {
            $status = $response['result']['success'];
        }

        return $status;
    }

    /**
     * Create a share of an item at the supplied path.
     *
     * @param string|array $path The paths of the item to be shared.
     * @param string $password The password of the shared to be created.
     * @return An instance of the share.
     * @throws Exception\InvalidArgumentException
     */
    public function createShare($path, $password = null) {
        $share = null;
        if (empty($path)) {
            throw new InvalidArgumentException('createShare function accepts a valid path or path array. Input was ' . $path);
        }

        $connection = new HTTPConnector($this->credential->getSession());
        $url = $this->credential->getRequestUrl(BitcasaConstants::METHOD_SHARES);
        $formParameters = array();
        $pathValues = array();
        if (is_array($path)) {
            foreach ($path as $value) {
                $pathValues[] = $value;
            }
            $formParameters = array('path' => $pathValues);

        } else {
            $formParameters = array('path' => $path);
        }

        if (!empty($password)) {
            $formParameters['password'] = $password;
        }
        $body = BitcasaUtils::generateParamsString($formParameters);
        $connection->setData($body);
        $connection->post($url);
        $response = $connection->getResponse(true);
        if (!empty($response) && !empty($response['result'])) {
            $share = Share::getInstance($this, $response['result']);
        }

        return $share;
    }

    /**
     * Retrieves the list of shares on the filesystem.
     *
     * @return The share list in user file system.
     */
    public function shares() {
        $shares = array();
        $connection = new HTTPConnector($this->credential->getSession());
        $url = $this->credential->getRequestUrl(BitcasaConstants::METHOD_SHARES);
        $connection->get($url);
        $response = $connection->getResponse(true);
        if (!empty($response) && !empty($response['result'])) {
            foreach ($response['result'] as $result) {
                $shares[] = Share::getInstance($this, $result);
            }
        }

        return $shares;
    }

    /**
     * Retrieves the items for a supplied share key.
     *
     * @param string $shareKey The supplied share key.
     * @param string $path The path to any folder inside the share.
     * @return The json response containing the items for the share.
     * @throws Exception\InvalidArgumentException
     */
    public function browseShare($shareKey, $path = null) {
        Assert::assertStringOrEmpty($shareKey, 1);
        $response = null;
        $pathParam = $shareKey;

        if ($path != null) {
            $pathParam .= '/' . $path;
        }

        $connection = new HTTPConnector($this->credential->getSession());
        $url = $this->credential->getRequestUrl(BitcasaConstants::METHOD_SHARES, $pathParam . '/meta');

        $connection->get($url);
        return $connection->getResponse(true);
    }

    /**
     * Receives the share item for a given share key to a path supplied.
     *
     * @param string $shareKey The supplied share key.
     * @param string $path The path to which the share files are retrieved to.
     * @param string $exists The action to take if the item already exists.
     * @return The success/failure status of the retrieve operation.
     */
    public function receiveShare($shareKey, $path, $exists = Exists::OVERWRITE) {
        Assert::assertStringOrEmpty($shareKey, 1);
        Assert::assertStringOrEmpty($path, 2);
        $success = false;
        $connection = new HTTPConnector($this->credential->getSession());
        $url = $this->credential->getRequestUrl(BitcasaConstants::METHOD_SHARES, $shareKey . '/');
        $body = BitcasaUtils::generateParamsString(array('path' => $path, 'exists' => $exists));
        $connection->setData($body);
        $connection->post($url);
        $response = $connection->getResponse(true);
        if (!empty($response) && !empty($response['result'])) {
            $success = true;
        }

        return $success;
    }

    /**
     * Deletes the share item for a supplied share key.
     *
     * @param string $shareKey The supplied share key.
     * @return The success/failure status of the delete operation.
     */
    public function deleteShare($shareKey) {
        Assert::assertStringOrEmpty($shareKey, 1);
        $connection = new HTTPConnector($this->credential->getSession());
        $url = $this->credential->getRequestUrl(BitcasaConstants::METHOD_SHARES, $shareKey . '/');
        $connection->delete($url);
        $deleted = true;

        return $deleted;
    }

    /**
     * Unlocks the share item of the supplied share key for the duration of the session.
     *
     * @param string $shareKey The supplied share key.
     * @param string $password The share password.
     * @return The success/failure status of the retrieve operation.
     * @throws Exception\InvalidArgumentException
     */
    public function unlockShare($shareKey, $password) {
        Assert::assertStringOrEmpty($shareKey, 1);
        Assert::assertStringOrEmpty($password, 2);
        $success = false;
        $connection = new HTTPConnector($this->credential->getSession());
        $url = $this->credential->getRequestUrl(BitcasaConstants::METHOD_SHARES, $shareKey . '/unlock');
        $body = BitcasaUtils::generateParamsString(array('password' => $password));
        $connection->setData($body);
        $connection->post($url);
        $response = $connection->getResponse(true);
        if (!empty($response) && !empty($response['result'])) {
            $success = true;
        }

        return $success;
    }

    /**
     * Alter the properties of a share item for a given share key with the supplied data.
     *
     * @param string $shareKey The supplied share key.
     * @param mixed[] $values The values to be changed.
     * @param string $password The share password.
     * @return An instance of the altered share.
     * @throws Exception\InvalidArgumentException
     */
    public function alterShare($shareKey, $values, $password = null) {
        Assert::assertStringOrEmpty($shareKey, 1);
        $connection = new HTTPConnector($this->credential->getSession());
        $url = $this->credential->getRequestUrl(BitcasaConstants::METHOD_SHARES, $shareKey . '/info');
        $formParameters = array();
        if (!empty($password)) {
            $formParameters['current_password'] = $password;
        }

        foreach ($values as $key => $value) {
            $formParameters[$key] = $value;
        }

        $body = BitcasaUtils::generateParamsString($formParameters);
        $connection->setData($body);
        $connection->post($url);
        return $connection->getResponse(true);
    }

    /**
     * @param string $path The item path.
     * @param int $startVersion The start version number.
     * @param int $endVersion The end version number.
     * @param int $limit The number of versions to retrieve.
     * @return The json response containing the version history.
     * @throws InvalidArgumentException
     */
    public function fileVersions($path, $startVersion, $endVersion, $limit) {
        Assert::assertStringOrEmpty($path, 1);
        $connection = new HTTPConnector($this->credential->getSession());
        $params = array();
        if ($startVersion != null) {
            $params['start-version'] = $startVersion;
        }
        if ($endVersion != null) {
            $params['stop-version'] = $endVersion;
        }
        if ($limit != null) {
            $params['limit'] = $limit;
        }
        $url = $this->credential->getRequestUrl(BitcasaConstants::METHOD_FILES,
            $path . BitcasaConstants::METHOD_VERSIONS, $params);
        $connection->get($url);
        return $connection->getResponse(true);
    }

    /**
     * Streams the content of a given file at the supplied path
     *
     * @param string $path The file path.
     * @return The file stream.
     * @throws Exception\InvalidArgumentException
     */
    public function fileRead($path) {
        Assert::assertStringOrEmpty($path, 1);
        $connection = new HTTPConnector($this->credential->getSession());
        $url = $this->credential->getRequestUrl(BitcasaConstants::METHOD_FILES, $path, array());
        $connection->get($url);
        return $connection->getResponse();
    }

    /**
     * Browses the Trash meta folder on the authenticated user’s account.
     *
     * @param $path The supplied path.
     * @return The error status or the returned items in trash.
     */
    public function listTrash($path = null) {
        $endpoint = BitcasaConstants::METHOD_TRASH;
        $connection = new HTTPConnector($this->credential->getSession());
        $url = $this->credential->getRequestUrl($endpoint, "/" . $path);
        $connection->get($url);
        return $connection->getResponse(true);
    }

    /**
     * @param $path The item path
     * @return The json response containing the status of the delete operation.
     * @throws \InvalidArgument
     */
    public function deleteTrashItem($path) {
        Assert::assertStringOrEmpty($path, 1);
        $endpoint = BitcasaConstants::METHOD_TRASH;
        $connection = new HTTPConnector($this->credential->getSession());
        $url = $this->credential->getRequestUrl($endpoint, "/" . $path);
        $connection->delete($url);
        return $connection->getResponse(true);
    }

    /**
     * Gets the download url for the specified file.
     *
     * @param string $path The file path.
     * @return The download url for the specified file.
     */
    public function downloadUrl($path) {
        $connection = new HTTPConnector($this->credential->getSession());
        $url = $this->credential->getRequestUrl(BitcasaConstants::METHOD_FILES, $path);
        return $connection->getRedirectUrl($url);
    }
}

?>
