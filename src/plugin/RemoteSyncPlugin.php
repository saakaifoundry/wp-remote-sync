<?php

require_once __DIR__."/../utils/Singleton.php";
require_once __DIR__."/../syncers/PostSyncer.php";
require_once __DIR__."/../syncers/AttachmentSyncer.php";
require_once __DIR__."/../controller/RemoteSyncApi.php";
require_once __DIR__."/../controller/RemoteSyncOperations.php";

/**
 * Remote sync plugin.
 */
class RemoteSyncPlugin extends Singleton {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->syncers=NULL;
		$this->api=NULL;
		$this->operations=NULL;
	}

	/**
	 * Get enabled syncer.
	 */
	public function getEnabledSyncers() {
		if (!$this->syncers) {
			$this->syncers=array();
			$this->syncers[]=new PostSyncer();
			$this->syncers[]=new AttachmentSyncer();
		}

		return $this->syncers;
	}

	/**
	 * Get syncer for type.
	 */
	public function getSyncerByType($type) {
		foreach ($this->getEnabledSyncers() as $syncer)
			if ($syncer->getType()==$type)
				return $syncer;

		throw new Exception("Can't sync: ".$type);
	}

	/**
	 * Install.
	 */
	public function install() {
		SyncResource::install();

		$syncers=$this->getEnabledSyncers();

		foreach ($syncers as $syncer)
			$syncer->install();
	}

	/**
	 * Get reference to the api.
	 */
	public function getApi() {
		if (!$this->api)
			$this->api=new RemoteSyncApi();

		return $this->api;
	}

	/**
	 * Get reference to operations object.
	 */
	public function getOperations() {
		if (!$this->operations)
			$this->operations=new RemoteSyncOperations();

		return $this->operations;
	}

	/**
	 * Make a call to the remote.
	 */
	public function remoteCall($method, $args=array(), $attachments=array()) {
		$args["action"]=$method;

		$url=get_option("rs_remote_site_url");
		if (!trim($url))
			throw new Exception("Remote site url not set");

		$url.="/wp-content/plugins/wp-remote-sync/api.php";
		$url.="?".http_build_query($args);

		$curl=curl_init($url);
		curl_setopt($curl,CURLOPT_RETURNTRANSFER,TRUE);

		if ($attachments) {
			curl_setopt($curl,CURLOPT_POST,1);

			$upload_base_dir=wp_upload_dir()["basedir"];

			$postfields=[];
			foreach ($attachments as $attachment) {
				$attachmentfilename="$upload_base_dir/$attachment";
				$postfields[$attachment]=new CurlFile(
					$attachmentfilename,
					"text/plain"
				);
			}

			curl_setopt($curl,CURLOPT_POSTFIELDS,$postfields);
		}

		$res=curl_exec($curl);
		$returnCode=curl_getinfo($curl,CURLINFO_HTTP_CODE);

		if ($returnCode!=200)
			throw new Exception("Unexpected return code: ".$returnCode."\n".$res);

		$parsedRes=json_decode($res,TRUE);

		if ($parsedRes===FALSE)
			throw new Exception("Unable to parse json... ".$res);

		return $parsedRes;
	}

	/**
	 * Get all remote resources of the specific type.
	 */
	public function getRemoteResources($type) {
		$infos=$this->remoteCall("ls",array(
			"type"=>$type
		));

		$remoteResources=[];

		foreach ($infos as $info) {
			$remoteResource=new RemoteResource($type, $info["globalId"], $info["revision"]);
			$remoteResources[]=$remoteResource;
		}

		return $remoteResources;
	}
}