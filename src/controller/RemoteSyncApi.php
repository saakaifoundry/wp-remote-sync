<?php

/**
 * Handle api calls.
 */
class RemoteSyncApi {

	private $plugin;

	/**
	 * Construct.
	 */
	public function __construct() {
		$this->calls=array("ls","get","put","add","del");
	}

	/**
	 * List.
	 */
	public function ls($args) {
		if (!isset($args["type"]))
			throw new Exception("Expected resource type for ls");

		$syncer=RemoteSyncPlugin::instance()->getSyncerByType($args["type"]);
		$syncer->updateSyncResources();
		$resources=$syncer->getSyncResources();
		$res=array();

		foreach ($resources as $resource) {
			if (!$resource->isDeleted()) {
				$res[]=array(
					"globalId"=>$resource->globalId,
					"revision"=>$resource->getRevision()
				);
			}
		}

		return $res;
	}

	/**
	 * Get resource
	 */
	public function get($args) {
		if (!$args["globalId"])
			throw new Exception("Expected parameter globalId");

		$resource=SyncResource::findOneBy("globalId",$args["globalId"]);
		$syncer=RemoteSyncPlugin::instance()->getSyncerByType($resource->type);
		$syncer->updateSyncResources();

		return array(
			"globalId"=>$resource->globalId,
			"revision"=>$resource->getRevision(),
			"type"=>$resource->type,
			"data"=>$resource->getData()
		);
	}

	/**
	 * Add a resource.
	 */
	public function add($args) {
		if (!$args["globalId"] ||
			!$args["data"] || !$args["type"])
			throw new Exception("Expected globalId, type and data.");

		$syncer=RemoteSyncPlugin::instance()->getSyncerByType($args["type"]);
		$syncer->updateSyncResources();

		$resource=SyncResource::findOneBy("globalId",$args["globalId"]);
		if ($resource)
			throw new Exception("Already exists!");

		$data=json_decode($args["data"],TRUE);
		if (!$data)
			$data=json_decode(stripslashes($args["data"]),TRUE);

		if (!$data)
			throw new Exception("Unable to parse json data");

		$localId=$syncer->createResource($data);
		$syncer->processAttachments($localId);

		$localResource=new SyncResource($syncer->getType());
		$localResource->localId=$localId;
		$localResource->globalId=$args["globalId"];
		$localResource->save();

		return array(
			"ok"=>1
		);
	}

	/**
	 * Put.
	 */
	public function put($args) {
		if (!$args["globalId"] ||
			!$args["baseRevision"] || !$args["data"])
			throw new Exception("Expected globalId, baseRevision and data.");

		$resource=SyncResource::findOneBy("globalId",$args["globalId"]);
		if (!$resource)
			throw new Exception("Doesn't exist locally");

		if ($args["baseRevision"]!=$resource->getRevision())
			throw new Exception("Wrong base revision, please pull.");

		$data=json_decode($args["data"],TRUE);
		if (!$data)
			$data=json_decode(stripslashes($args["data"]),TRUE);

		if (!$data)
			throw new Exception("Unable to parse json data");

		$syncer=$resource->getSyncer();
		//$syncer->updateSyncResources();
		$syncer->updateResource($resource->localId,$data);
		$syncer->processAttachments($resource->localId);

		$resource->save();

		return array(
			"ok"=>1
		);
	}

	/**
	 * Delete.
	 */
	public function del($args) {
		$resource=SyncResource::findOneBy("globalId",$args["globalId"]);
		if (!$resource)
			throw new Exception("Doesn't exist locally");

		$syncer=$resource->getSyncer();
		$syncer->deleteResource($resource->localId);

		if (!$resource->getBaseRevision())
			$resource->delete();

		return array(
			"ok"=>1
		);
	}

	/**
	 * Handle exception in api call.
	 */
	public function handleException($exception) {
		$res=array(
			"error"=>TRUE,
			"message"=>$exception->getMessage()
		);

		http_response_code(500);
		echo json_encode($res);
		exit();
	}

	/**
	 * Handle api call.
	 */
	public function handleApiCall($call, $params) {
		set_exception_handler(array($this,"handleException"));

		if (!in_array($call,$this->calls))
			throw new Exception("Unknown api call: $call");

		$res=call_user_func(array($this,$call),$params);

		echo json_encode($res);
		exit();
	}
}