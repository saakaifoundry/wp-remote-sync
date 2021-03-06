<?php

require_once __DIR__."/../plugin/AResourceSyncer.php";

/**
 * Sync wordpress attachments.
 */
class AttachmentSyncer extends AResourceSyncer {

	/**
	 * Construct.
	 */
	public function __construct() {
		parent::__construct("attachment");
	}

	/**
	 * Get local id by slug.
	 */
	private function getIdBySlug($slug) {
		global $wpdb;

		$q=$wpdb->prepare("SELECT ID FROM {$wpdb->prefix}posts WHERE post_name=%s",$slug);
		$id=$wpdb->get_var($q);

		if ($wpdb->last_error)
			throw new Exception($wpdb->last_error);

		return $id;
	}

	/**
	 * Get local id by slug.
	 */
	private function getSlugById($postId) {
		$post=get_post($postId);
		if (!$post)
			return NULL;

		if ($post->post_type!="attachment")
			throw new Exception("expected post to be attachment");

		return $post->post_name;
	}

	/**
	 * List current local resources.
	 */
	public function listResourceSlugs() {
		$slugs=array();

		$q=new WP_Query(array(
			"post_type"=>"attachment",
			"post_status"=>"any",
			"posts_per_page"=>-1
		));
		$posts=$q->get_posts();

		foreach ($posts as $post) {
			if ($post->post_name)
				$slugs[]=$post->post_name;
		}

		return $slugs;
	}

	/**
	 * Get resource attachment.
	 */
	public function getResourceAttachments($slug) {
		$localId=$this->getIdBySlug($slug);
		$res=array();

		$res[]=get_post_meta($localId,"_wp_attached_file",TRUE);
		$meta=get_post_meta($localId,"_wp_attachment_metadata",TRUE);

		if (isset($meta["file"]))
			$res[]=$meta["file"];

		if (isset($meta["file"]) && isset($meta["file"]) && $meta["sizes"]){
			foreach ($meta["sizes"] as $type=>$size)
				$res[]=dirname($meta["file"])."/".$size["file"];
		}
		
		return $res;
	}

	/**
	 * Get post by local id.
	 */
	public function getResource($slug) {
		$localId=$this->getIdBySlug($slug);
		$post=get_post($localId);

		if (!$post)
			return NULL;

		if ($post->post_status=="trash")
			return NULL;

		return array(
			"post_title"=>$post->post_title,
			"post_name"=>$post->post_name,
			"post_mime_type"=>$post->post_mime_type,
			"_wp_attached_file"=>get_post_meta($localId,"_wp_attached_file",TRUE),
			"_wp_attachment_metadata"=>get_post_meta($localId,"_wp_attachment_metadata",TRUE)
		);
	}

	/**
	 * Update a local resource with data.
	 */
	function updateResource($slug, $data) {
		$localId=$this->getIdBySlug($slug);
		$post=get_post($localId);

		if ($slug!=$data["post_name"])
			throw new Exception("Sanity check failed, slug!=post_name");

		$post->post_name=$data["post_name"];
		$post->post_title=$data["post_title"];
		$post->post_mime_type=$data["post_mime_type"];
		wp_update_post($post);

		update_post_meta($localId,"_wp_attached_file",$data["_wp_attached_file"]);
		update_post_meta($localId,"_wp_attachment_metadata",$data["_wp_attachment_metadata"]);
	}

	/**
	 * Create a local resource.
	 */
	function createResource($slug, $data) {
		if ($slug!=$data["post_name"])
			throw new Exception("Sanity check failed, slug!=post_name");

		$id=wp_insert_post(array(
			"post_name"=>$slug,
			"guid"=>$data["guid"],
			"post_title"=>$data["post_title"],
			"post_mime_type"=>$data["post_mime_type"],
			"post_type"=>"attachment"
		));

		update_post_meta($id,"_wp_attached_file",$data["_wp_attached_file"]);
		update_post_meta($id,"_wp_attachment_metadata",$data["_wp_attachment_metadata"]);

		return $id;
	}

	/**
	 * Delete a local resource.
	 */
	function deleteResource($slug) {
		$localId=$this->getIdBySlug($slug);
		wp_delete_post($localId);
	}
}