<?php
/**
* Project:		positweb
* File name:	dao.php
* Description:	database access
* PHP version 5, mysql 5.0
*
* LICENSE: This source file is subject to LGPL license
* that is available through the world-wide-web at the following URI:
* http://www.gnu.org/copyleft/lesser.html
*
* @author       Antonio Alcorn
* @copyright    Humanitarian FOSS Project@Trinity (http://hfoss.trincoll.edu), Copyright (C) 2009.
* @package		posit
* @subpackage
* @tutorial
* @license  http://www.gnu.org/copyleft/lesser.html GNU Lesser General Public License (LGPL)
* @version
*/

function dbConnect() {
	$host 		= DB_HOST;
	$user 		= DB_USER;
	$pass 		= DB_PASS;
	$db_name 	= DB_NAME;
	
	try {
	    $db = new PDO("mysql:host=$host;dbname=$db_name", $user, $pass);
	} catch (PDOException $e) {
	    errorMessage("Database error: ". $e->getMessage());
	    die();
	}
	
	mysql_connect($host, $user, $pass);
	mysql_select_db($db_name);
	
	return $db;
}

/**
 * 
 * The class for accessing the database
 *	
 */
class DAO {
	private $db;
	
	function DAO() {
		$this->db = dbConnect();
	}
	
	 /**
	  *  returns a comma-delimited list of all finds since last sync for a given device
	  *  @param unknown_type $imei the device's id
	  */
	  function getDeltaFindsIds($auth_key, $pid) {
      	      Log::getInstance()->log("getDeltaFindsIds: $auth_key");
		      						 
   	      // Get the timestamp of the last sync with this device
							   
	      if ($pid > -1){
	   	 $stmt = $this->db->prepare(
		       "SELECT MAX(sync_history.time) FROM sync_history WHERE sync_history.project_id = '$pid' AND auth_key = :auth_key"); 
	       }else{
 	         $stmt = $this->db->prepare(
     	               "SELECT MAX(sync_history.time) FROM sync_history WHERE sync_history.project_id = -1 AND auth_key = :auth_key"); 
     	       }
									      
	       $stmt->bindValue(":auth_key", $auth_key);
  	       $stmt->execute();
 	       $result = $stmt->fetch(PDO::FETCH_NUM);
      	       //print_r($result);
	       $time = $result[0];
																				       	     
	       Log::getInstance()->log("getDeltaFindsIds: Max Time = |$time| and PID = $pid");
               
                //  If there is no MAX time, this is a new device, so get all Finds -- i.e. some may have been
                //   input from other phones

	        if ($time == NULL) {
	     	       Log::getInstance()->log("getDeltaFindsIds: IF time = $time");	
  		       $res = mysql_query(
		       	 "SELECT DISTINCT find_history.find_guid FROM find_history,find WHERE find_history.auth_key != '$auth_key' AND find.guid=find_history.find_guid AND find.project_id = '$pid' AND find.deleted=0" ) 
	 	 	  or die(mysql_error());  
                } else {
		       Log::getInstance()->log("getDeltaFindsIds: ELSE time = $time");	
 		       $res = mysql_query(
			  "SELECT DISTINCT find_history.find_guid FROM find_history,find WHERE TIMESTAMPDIFF(SECOND,'$time',time) > 0 AND find.guid=find_history.find_guid AND find.project_id = '$pid' AND find.deleted=0") 
	 	 	  or die(mysql_error());  
                }

//SELECT DISTINCT find_history.find_guid FROM find_history, find WHERE find_history.imei != '351677030043731' AND find.guid = find_history.find_guid AND find.project_id = 6


    	       // Get a list of the Finds (guids) that have changed since the last update

		while ($row = mysql_fetch_row($res)) {
		      $list .= "$row[0],";
	        }
		$this->createLog("I","getDeltaFindsIds",$list);					
		Log::getInstance()->log("getDeltaFindsIds: $list");
		return $list;
	    }

    /**
     * records a record in the sync_history table
     * @param unknown_type $imei
     */
     function recordSync($imei, $authKey, $projectId) {
 	 Log::getInstance()->log("recordSync"."Imei:".$imei. "Authkey:".$authKey. "Projectid:".$projectId);
										 						  
         if ($projectId > -1){
 	    $stmt = $this->db->prepare(
	      	   "INSERT INTO sync_history (imei, auth_key, project_id) VALUES (:imei,:authkey,:projectid)"
   	    ); 
	    $stmt->bindValue(":projectid", $projectId);
	  }else{
	     $stmt = $this->db->prepare(
		"INSERT INTO sync_history (imei, auth_key) VALUES (:imei,:authkey)"
	     ); 	       
          }
          $stmt->bindValue(":imei", $imei);
          $stmt->bindValue(":authkey", $authKey);
          $stmt->execute();
          $lastid = $this->db->lastInsertId();
	  $this->createLog("I","lastInsertId","Last id = ".$lastid);
	  return $lastid;
     }


	/**
	 * Renames expedition and returns a boolean based on the success of the query
	 * @param $expId
	 * @param $new
	 * @return Boolean Successs
	 */
	function renameExpedition($expId, $newName){
		$query = sprintf("UPDATE expedition SET name='%s' where id='%s'",
		mysql_real_escape_string($newName),
		mysql_real_escape_string($expId));
		$stmt = $this->db->prepare($query);
		$stmt->execute();
		
	}
	 
	 /**
	  * Creates a new entry in the form database for using the title, userId and form data
	  * @param $formData, $title, $userId
	  */
	 function newForm($title, $userId, $formData,$xml){
	 	$query = sprintf("INSERT INTO forms (title, user_id, form, xml) VALUES('%s', '%s', '%s', '%s')",
	 	mysql_real_escape_string($title),
	 	mysql_real_escape_string($userId),
	 	mysql_real_escape_string($formData),
	 	/*
	 	 * this is because the colon used in the namespace identifier gets escaped by the command
	 	 * using something like {colon} makes it all to obvious
	 	 */
	 	mysql_real_escape_string(str_replace(":", "{colon}",$xml))); 
	 	$stmt = $this->db->prepare($query);	 	
	 	$stmt->execute();
	 }

	 /**
	  * Updates the form that has the given title and user id using the form data.
	  * @param $title
	  * @param $userId
	  * @param $formData
	  */
	 function updateForm($title, $userId, $formData, $xml){
	 	$query = sprintf("UPDATE forms SET  form = '%s' WHERE user_id = '%s' and title = '%s' and xml = '%s'",
	 	mysql_real_escape_string($formData),
	 	mysql_real_escape_string($userId),
	 	mysql_real_escape_string($title),
	 	/*
	 	 * this is because the colon used in the namespace identifier gets escaped by the command
	 	 * using something like {colon} makes it all to obvious
	 	 */
	 	mysql_real_escape_string(str_replace(":", "{colon}",$xml)));
	 	$stmt = $this->db->prepare($query);	 	
	 	$stmt->execute();
	 }

	 /**
	  * Returns form xml data associated with form id.
	  * @param $formId
	  * @return Associative Array
	  */
	 function loadFormXml($id){
	 	$query = sprintf("SELECT xml FROM `forms` WHERE id = '%s'", 
	 	mysql_real_escape_string($id));
	 	$stmt = $this->db->prepare($query);
	 	$stmt->execute();
	 	$return = $stmt->fetch(PDO::FETCH_NUM);
	 	/*
	 	 * replace back the colon character that was escaped before
	 	 */
	 	return str_replace( "{colon}", ":",$return[0]);
	 }
	 
	 /**
	  * Returns form data associated with title and user id.
	  * @param $userId
	  * @param $title
	  * @return Associative Array
	  */
	 function loadForm($userId, $title){
	 	$query = sprintf("SELECT form FROM `forms` WHERE user_id = '%s' and title = '%s'", 
	 	mysql_real_escape_string($userId),
	 	mysql_real_escape_string($title));
	 	$stmt = $this->db->prepare($query);
	 	$stmt->execute();
	 	$return = $stmt->fetch(PDO::FETCH_NUM);
	 	return $return[0];
	 }
	 
 /**
	  * Returns all rows in the forms table associated with the user id
	  * @param $userId
	  * @return Associative Array
	  */
	 function listForms($userId){
	 	$query = sprintf("SELECT title FROM forms where user_id='%s'", mysql_real_escape_string($userId));
	 	$stmt = $this->db->prepare($query);
	 	$stmt->execute();
	 	return $stmt->fetchAll(PDO::FETCH_ASSOC);
	 }
	 /*
	  * List all forms 
	  * USE ONLY IN ODK --- DEMO PURPOSES ONLY -Prasanna
	  */
	 function listAllForms (){
	 	$query = "SELECT id,title FROM forms";
	 	$stmt = $this->db->prepare($query);
	 	$stmt->execute();
	 	return $stmt->fetchAll(PDO::FETCH_ASSOC);
	 }
	 
	 /**
	  * Checks if the form name already exists for the user. Returns true if it does and false if it does not.
	  * @param $title
	  * @param $userId
	  * @return Boolean
	  */
	 function checkFormName($title, $userId){
	 	$query = sprintf("SELECT * FROM `forms` WHERE user_id = '%s' and title = '%s'", 
	 	mysql_real_escape_string($userId),
	 	mysql_real_escape_string($title));
	 	$stmt = $this->db->prepare($query);
	 	$stmt->execute();
	 	$result=$stmt->fetch(PDO::FETCH_NUM);
	 	if(!isset($result[0]))
	 		return false;
	 	return true; 
	 }
	 /**
	  * Deletes the form associated with the title and user id.
	  * @param $title
	  * @param $userId
	  * @return n/a
	  */
	 function deleteForm($title, $userId){
	 	$query = sprintf("DELETE FROM `forms` WHERE title = '%s' and user_id = '%s'", 
	 	mysql_real_escape_string($title),
	 	mysql_real_escape_string($userId));
	 }
	 /**
	  * Returns all rows in the forms table associated with the user id
	  * @param $userId
	  * @return Associative Array
	  */
	 function createLog($type,$tag,$message){
	 	/*$stmt=$this->db->prepare("INSERT INTO logs (type,tag,message) VALUES(:type,:tag,:message)");
	 	
	 	
	 	$stmt->bindValue(":type",$type);
	 	$stmt->bindValue(":tag",$tag);
	 	$stmt->bindValue(":message",$message);
	 	
	 	$stmt->execute();*/
	 }
	 	 /**
	 * Returns the logs for the selected page number
	 */
	 function numLogPages(){
	 	$stmt=$this->db->prepare("SELECT COUNT(id) from logs");
	 	$stmt->execute();
	 	$result=$stmt->fetch(PDO::FETCH_NUM);
		$numPages=ceil($result[0]/30);
	 	return $numPages;
	 }
	 /**
	 * Returns the logs for the selected page number
	 */
	 function getLogs($pageNumber){
		$minPage=($pageNumber-1)*30;
		$maxPage=30;
		$stmt=$this->db->prepare("SELECT * FROM logs LIMIT :minPage,:maxPage");
	 	$stmt->bindValue(":minPage",$minPage);
	 	$stmt->bindValue(":maxPage",$maxPage);
	 	$stmt->execute();
	 	$result=$stmt->fetchAll(PDO::FETCH_ASSOC);
	 	return $result;
	 }
	/**
	 * get user from the user ID
	 * @param unknown_type $userId
	 */
	function getUser($userId) {
		$this->createLog("I","getUser","$userId");

		$stmt = $this->db->prepare(
			"SELECT email, first_name, last_name, privileges, create_time FROM user WHERE id = :userId"
		);
		
		$stmt->bindValue(":userId", $userId);
		$stmt->execute();
		
		return $stmt->fetch(PDO::FETCH_ASSOC);
	}
	
	/**
	 * get userID from the email
	 * @param unknown_type $email
	 */
	function getUserId($email) {

		$stmt = $this->db->prepare(
			"SELECT id FROM user WHERE email = :email"
		);
		
		$stmt->bindValue(":email", $email);
		if ($stmt->execute()){;
		
			$result = $stmt->fetch(PDO::FETCH_ASSOC);
			return $result["id"];
		}else {
			return false;
		}

	}
	/**
	 * creates a new project
	 * @param unknown_type $name
	 * @param unknown_type $description
	 */
	function newProject($name, $description, $userId) {
		$this->createLog("I","New Project","Name:".$name." Description:".$description."User ID: ".$userId);		

		$name = addslashes($name);
		$description = addslashes($description);
		$stmt = $this->db->prepare(
			"INSERT INTO project (name, description) VALUES (:name, :description)"
		); // or print_r($this->db->errorInfo()) && die();
		
		$stmt->bindValue(":name", $name);
		$stmt->bindValue(":description", $description);
		
		$stmt->execute();
		
		$result = $stmt->fetch(PDO::FETCH_ASSOC);
		
		$lastId = $this->db->lastInsertId();
		
		$stmt = $this->db->prepare(
			"INSERT INTO user_project (user_id, project_id, role)
				VALUES (:userId, :projectId, 'owner')"
		); 
		
		$stmt->bindValue(":userId", $userId);
		$stmt->bindValue(":projectId", $lastId);
		
		$stmt->execute() or print_r($stmt->errorInfo()) && die();

		return "Project with ID " . $lastId . "inserted successfully.";
		//die("[Error " .__FILE__." at ".__LINE__."] ". $this->db->errorInfo());
		/*$stmt = $this->db->prepare("INSERT INTO project (name) VALUES ('$name')");*/
		
	}
	
	/**
	 * Create a new project from the phone using the auth key
	 * @param unknown_type $userId
	 */
	function newProjectFromPhone($name, $description, $authKey) {
		
		$stmt = $this->db->prepare(
			"SELECT user_id FROM device WHERE auth_key = :authKey"
		); 
		
		$stmt->bindValue(":authKey", $authKey);
		$stmt->execute();
		$result = $stmt->fetchAll(PDO::FETCH_ASSOC);

		if(is_array($result))
			$this->newProject($name, $description, $result[0]["user_id"]);
	}
	/**
	 * gets an associative array of all the projects that are accessible to the entity
	 * @param unknown_type $permissionType
	 */
	function getProjects($permissionType = PROJECTS_ALL) {
		Log::getInstance()->log("getProjects: $permissionType");

		if($permissionType == PROJECTS_OPEN)
			$whereClause = "where permission_type = 'open' and deleted=0";
		else if($permissionType == PROJECTS_CLOSED)
			$whereClause = "where permission_type = 'closed' and deleted=0";
		else
			$whereClause = "where deleted=0";
			
		$stmt = $this->db->prepare(
			"select id, name, description, create_time, permission_type
			 from project ". $whereClause
		);
//		$stmt->execute();
		$stmt->execute() or die("[Error MySQl on ".__FILE__."at ".__LINE__."]" . mysql_error());
		$result = $stmt->fetchAll(PDO::FETCH_ASSOC);
		$this->createLog("I","getProjects"," $result");

		return $result;
	}
	/**
	 * Get the projects accessible to the user, whether or not
	 * they are the owner
	 * @param unknown_type $userId
	 */
	function getUserProjects($userId) {
	    $this->createLog("I","getUserProjects"," $userId");

		$stmt = $this->db->prepare(
			"SELECT project.name, project.id, project.description, user_project.role
			 FROM project 
			 JOIN user_project
			 ON project.id = user_project.project_id 
			 AND user_project.user_id = :userId
			 WHERE deleted=0"
			);
		
		$stmt->bindValue(":userId", $userId);
		$stmt->execute();
		$result = $stmt->fetchAll(PDO::FETCH_ASSOC);
		
		return $result;
	}
	
	/**
	 * Get projects where the user specified is the owner.
	 * @param unknown_type $ownerId
	 */
	function getOwnerProjects($ownerId) {
		$stmt = $this->db->prepare(
			"SELECT project.name, project.id, project.description
			 FROM project 
			 JOIN user_project
			 ON project.id = user_project.project_id 
			 AND user_project.user_id = :ownerId AND user_project.role = 'owner'
			 WHERE deleted=0"
		);
		
		$stmt->bindValue(":ownerId", $ownerId);
		$stmt->execute();
		$result = $stmt->fetchAll(PDO::FETCH_ASSOC);
		
		return $result;	
	}
	/**
	 * Gives a user access to a project
	 * @param unknown_type $userId
	 */
	function shareProject($ownerId, $userId, $projectId) {
		$stmt = $this->db->prepare(
			"select user_id from user_project 
			 WHERE role = 'owner' AND project_id = :projectId 
			 AND user_id = :userId"
		);
		$stmt->bindValue(":projectId", $projectId);
		$stmt->bindValue(":userId", $userId);
		$stmt->execute();
		$result = $stmt->fetchAll(PDO::FETCH_ASSOC);
		
		if (is_array($result)) {
			$stmt = $this->db->prepare(
			"INSERT INTO user_project (user_id, project_id, role)
				VALUES (:userId, :projectId, 'user')"
			);
			$stmt->bindValue(":projectId", $projectId);
			$stmt->bindValue(":userId", $userId);
			$stmt->execute();
		}
	}
	/**
	 * get all the finds for a project
	 * @param unknown_type $projectId
	 */
	function getFinds($projectId, $lastTime = null) {
		Log::getInstance()->log("getFinds, projectId = $projectId");
		if($lastTime != null){
			$stmt = $this->db->prepare("select id, guid, name, description, add_time, modify_time,
			latitude, longitude, revision from find where add_time > :lastTime
			 and project_id = :projectId and deleted=0 order by add_time"
		);	
		$stmt->bindValue(":lastTime", $lastTime);
		}else{
			$stmt = $this->db->prepare("select id, guid, name, description, add_time, modify_time,
				latitude, longitude, revision from find where project_id = :projectId and deleted=0 order by add_time"
			);	
		}
		$stmt->bindValue(":projectId", $projectId);
		$stmt->execute();
		$temp = $stmt->fetchAll(PDO::FETCH_ASSOC);
		$result = array();
		
		foreach ($temp as $find) {
			$stmt =  $this->db->prepare("select id from photo where guid = :id");
			$stmt->bindValue(":id", $find["guid"]);
			$stmt->execute();
			$imageResult = $stmt->fetchAll(PDO::FETCH_ASSOC);
			
			$find["images"] = array();
			
			foreach($imageResult as $image) 
				$find["images"][] = $image["id"];

			$stmt = $this->db->prepare("select id from video where find_id = :id");
			$stmt->bindValue(":id", $find["id"]);
			$stmt->execute();
			$videoResult = $stmt->fetchAll(PDO::FETCH_ASSOC);
			
			$find["videos"] = array();
			
			foreach($videoResult as $video) {
				$find["videos"][] = $video["id"];
			}
			
			$stmt = $this->db->prepare("select id from audio where find_id= :id");
			$stmt->bindValue(":id", $find["id"]);
			$stmt->execute();
			$audioResult = $stmt->fetchAll(PDO::FETCH_ASSOC);
			
			$find["audioClips"] = array();
			
			foreach($audioResult as $audio) {
				$find["audioClips"][] = $audio["id"];
			}
			
			$result[] = $find;
		}

		return $result;
	}
	/**
	 * get all the finds for a project that meet the search criteria for the project name
	 * @param unknown_type $projectId
	 */
	function searchForFinds($projectId, $searchFor, $lastTime = null) {
		Log::getInstance()->log("getFinds, projectId = $projectId");
		if($lastTime != null){
			$stmt = $this->db->prepare("select id, guid, name, description, add_time, modify_time,
			latitude, longitude, revision from find where add_time > :lastTime
			 and project_id = :projectId and name like :searchFor and deleted=0 order by add_time"
		);	
		$stmt->bindValue(":lastTime", $lastTime);
		}else{
			$stmt = $this->db->prepare("select id, guid, name, description, add_time, modify_time,
				latitude, longitude, revision from find where project_id = :projectId and name like :searchFor and deleted=0 order by add_time"
			);	
		}
		$stmt->bindValue(":projectId", $projectId);
		$stmt->bindValue(":searchFor", "%" . $searchFor . "%");
/**		$stmt->bindValue(":searchFor", "%e%");*/
		$stmt->execute();
		$temp = $stmt->fetchAll(PDO::FETCH_ASSOC);
		$result = array();
		
		foreach ($temp as $find) {
			$stmt =  $this->db->prepare("select id from photo where guid = :id");
			$stmt->bindValue(":id", $find["guid"]);
			$stmt->execute();
			$imageResult = $stmt->fetchAll(PDO::FETCH_ASSOC);
			
			$find["images"] = array();
			
			foreach($imageResult as $image) 
				$find["images"][] = $image["id"];

			$stmt = $this->db->prepare("select id from video where find_id = :id");
			$stmt->bindValue(":id", $find["id"]);
			$stmt->execute();
			$videoResult = $stmt->fetchAll(PDO::FETCH_ASSOC);
			
			$find["videos"] = array();
			
			foreach($videoResult as $video) {
				$find["videos"][] = $video["id"];
			}
			
			$stmt = $this->db->prepare("select id from audio where find_id= :id");
			$stmt->bindValue(":id", $find["id"]);
			$stmt->execute();
			$audioResult = $stmt->fetchAll(PDO::FETCH_ASSOC);
			
			$find["audioClips"] = array();
			
			foreach($audioResult as $audio) {
				$find["audioClips"][] = $audio["id"];
			}
			
			$result[] = $find;
		}

		return $result;
	}
	/**
	 * get all the finds for a project that meet the search criteria for the project name and description
	 * @param unknown_type $projectId
	 */
	function advancedSearchForFinds($projectId, $searchFor, $descr, $lastTime = null) {
		Log::getInstance()->log("getFinds, projectId = $projectId");
		if($lastTime != null){
			$stmt = $this->db->prepare("select id, guid, name, description, add_time, modify_time,
			latitude, longitude, revision from find where add_time > :lastTime
			 and project_id = :projectId and name like :searchFor and description like :descr and deleted=0 order by add_time"
		);	
		$stmt->bindValue(":lastTime", $lastTime);
		}else{
			$stmt = $this->db->prepare("select id, guid, name, description, add_time, modify_time,
				latitude, longitude, revision from find where project_id = :projectId and name like :searchFor and description like :descr and deleted=0 order by add_time"
			);	
		}
		$stmt->bindValue(":projectId", $projectId);
		$stmt->bindValue(":searchFor", "%" . $searchFor . "%");
		$stmt->bindValue(":descr", "%" . $descr . "%");
/**		$stmt->bindValue(":searchFor", "%e%");*/
		$stmt->execute();
		$temp = $stmt->fetchAll(PDO::FETCH_ASSOC);
		$result = array();
		
		foreach ($temp as $find) {
			$stmt =  $this->db->prepare("select id from photo where guid = :id");
			$stmt->bindValue(":id", $find["guid"]);
			$stmt->execute();
			$imageResult = $stmt->fetchAll(PDO::FETCH_ASSOC);
			
			$find["images"] = array();
			
			foreach($imageResult as $image) 
				$find["images"][] = $image["id"];

			$stmt = $this->db->prepare("select id from video where find_id = :id");
			$stmt->bindValue(":id", $find["id"]);
			$stmt->execute();
			$videoResult = $stmt->fetchAll(PDO::FETCH_ASSOC);
			
			$find["videos"] = array();
			
			foreach($videoResult as $video) {
				$find["videos"][] = $video["id"];
			}
			
			$stmt = $this->db->prepare("select id from audio where find_id= :id");
			$stmt->bindValue(":id", $find["id"]);
			$stmt->execute();
			$audioResult = $stmt->fetchAll(PDO::FETCH_ASSOC);
			
			$find["audioClips"] = array();
			
			foreach($audioResult as $audio) {
				$find["audioClips"][] = $audio["id"];
			}
			
			$result[] = $find;
		}

		return $result;
	}

	/**
	 * get a specific find
	 * @param unknown_type $id
	 */
	function getFind($guid) {
		Log::getInstance()->log("getFind: guid = $guid");

		$stmt = $this->db->prepare("select project_id, guid, name, description, add_time, modify_time, 
			latitude, longitude, revision from find where guid = :guid");		
		$stmt->bindValue(":guid", $guid);
		$stmt->execute();
		$temp = $stmt->fetchAll(PDO::FETCH_ASSOC);
		
/********* Try to return the thumbnail
		$stmt = $this->db->prepare("select imei, data_full from photo where guid = :id");
		$stmt->bindValue(":id", $guid);
		$stmt->execute();
		$imageResult = $stmt->fetchAll(PDO::FETCH_ASSOC);
			
		$find["images"] = array();
		foreach($imageResult as $image) {
                        $img = "data:image/jpeg;base64," . base64_encode($image["data_thumb"]);
                        $find["img"] = $img;
                        $find["images"][] = $image["guid"];
		}
		
**************/
/*********
		foreach ($temp[0] as $key=>$value) {
			$this->createLog("I","getFind temp: $key = $value");
		}    				
***************/

		$result = array();
		$result[0]["find"]= $temp[0];
//		$this->createLog("I","getFind length of record"," " . count($result[0]));

		// Get this Find's images
                //
		$result[0]["images"] = array();
		$stmt = $this->db->prepare("select id from photo where guid = :id");
//		$stmt = $this->db->prepare("select data_full from photo where guid = :id");
		$stmt->bindValue(":id", $guid);
		$stmt->execute();
		$imageResult = $stmt->fetchAll(PDO::FETCH_ASSOC);

		
		// Currently only 1 image can be displayed on the phone.  So this will display the 
		//  last image. 
		// TODO:  Upgrade to store multiple images per find.
		foreach($imageResult as $image) {
//			$result[0]["img"] = "data:image/jpeg;base64," .  base64_encode($image["data_full"]);
//			$result[0]["images"][] = "data:image/jpeg;base64," .  base64_encode($image["data_full"]);
			$result[0]["images"][] = $image["id"];
		}
//		Log::getInstance()->log("getFind: number of images = " . count($result[0]["images"])   . " " . $result[0]["images"][0]);
//		Log::getInstance()->log("getFind: image = " . $result[0]["img"]);
		
/************
		$result[0]["audios"] = array();
		$stmt = $this->db->prepare("select id from audio where find_id = :id");
		$stmt->bindValue(":id", $id);
		$stmt->execute();
		$audioResult = $stmt->fetchAll(PDO::FETCH_ASSOC);

		foreach($audioResult as $audio) {
			$result[0]["audios"][] = $audio["id"];
		}

		$result[0]["videos"] = array();
		$stmt = $this->db->prepare("select id from video where find_id = :id");
		$stmt->bindValue(":id", $id);
		$stmt->execute();
		$videoResult = $stmt->fetchAll(PDO::FETCH_ASSOC);

		foreach($videoResult as $video) {
			$result[0]["videos"][] = $video["id"];
		}
*********************/

		$stmt = $this->db->prepare("select id from find where guid = :id");
		$stmt->bindValue(":id",$guid);
		$stmt->execute();
		$idResult = $stmt->fetchAll(PDO::FETCH_ASSOC);
		$id = $idResult[0]["id"];

		$stmt = $this->db->prepare("select data from find_extension where find_id = :id");
		$stmt->bindValue(":id", $id);
		$stmt->execute();
		$extensionResult = $stmt->fetchAll(PDO::FETCH_ASSOC);
		$result[0]["extension"]=$extensionResult[0]["data"];
		$xdata = $result[0]["extension"];
		Log::getInstance()->log("getFind: extra data = $xdata");

//		foreach($imageResult as $image) {
//			$result[0]["images"][] = $image["id"];
//		}

//		Log::getInstance()->log("getFind: image =" .  $result[0]["img"]);
		Log::getInstance()->log("getFind: number of images =" .  count($result[0]["images"]));

		return $result[0];
	
	}
	/**
	 * get a project object
	 * @param $id
	 */
	function getProject($id) {
		$this->createLog("I","getProject"," $id");

		$stmt = $this->db->prepare(
			"select id, name, create_time, permission_type, deleted
			 from project where id = :id");
		
		$stmt->bindValue(":id", $id);	
		$stmt->execute();
		$result = $stmt->fetch(PDO::FETCH_ASSOC);
		
		return $result;
	}
	
	function getProjectUsers($projectId) {
		$stmt = $this->db->prepare(
			"select user_id from user_project 
			 where project_id = :projectId"
		);
		
		$stmt->bindValue(":projectId", $projectId);
		$stmt->execute();
		$result = $stmt->fetch(PDO::FETCH_ASSOC);
		
		return $result;
	}
	/**
	 * get a picture
	 * @param unknown_type $pictureId
	 */
	function getPicture($pictureId){
		$this->createLog("I","getPicture"," $pictureId");

		$stmt = $this->db->prepare(
			"select id,guid,mime_type,data_full,data_thumb from photo
			where id = :id"
			
		);
//		print_r($this->db->errorInfo());
		$stmt->bindValue(':id', $pictureId);
		$stmt->execute();
		return $stmt->fetch(PDO::FETCH_ASSOC);
	}
	/**
	 * get all the pictures associated to a find
	 * @param unknown_type $findId
	 */
	function getPicturesByFind($guid){
		$this->createLog("I","getPicturesByFind"," $guid");
		$stmt = $this->db->prepare(
			"select guid,mime_type,data_full,data_thumb, project_id, identifier from photo
			where guid = :guid"
			
		);
		$stmt->bindValue(":guid", $guid);
		$stmt->execute();
		$temp = $stmt->fetchAll(PDO::FETCH_ASSOC);
		$result = array();
		foreach ($temp as $picture) {
			$result[] = $picture;
		}
		return $result;
	}
	/**
	 * get the video by Id
	 * @param unknown_type $videoId
	 */
	function getVideo($videoId) {
		$stmt = $this->db->prepare("select id, find_id, mime_type, data_path from video where id=:id");
		$stmt->bindValue(':id', $videoId);
		$stmt->execute();
		return $stmt->fetch(PDO::FETCH_ASSOC);
	}
	/**
	 * get audio clip by Id
	 * @param unknown_type $audioId
	 */
	function getAudioClip($audioId) {
		$stmt = $this->db->prepare("select id, find_id, mime_type, data_path from audio where id=:id");
		$stmt->bindValue(':id', $audioId);
		$stmt->execute();
		return $stmt->fetch(PDO::FETCH_ASSOC);
	}
	/**
	 * verify the login based on email address and password entered
	 * @param $email
	 * @param $pass
	 */
	function checkLogin($email, $pass) {
		$stmt = $this->db->prepare(
			"SELECT id, first_name, last_name
			 FROM user
			 WHERE email = :email AND password = SHA1(:pass)"
		) or print_r($this->db->errorInfo()) && die();
		$stmt->bindValue(':email', $email);
		$stmt->bindValue(':pass', $pass);
		
		$stmt->execute();
		
		if($result = $stmt->fetch(PDO::FETCH_ASSOC))
			return $result;
		else
			return false;
	}
	
	/**
	 * delete a find
	 * @param unknown_type $findId
	 * THIS FUNCTION WILL BE COMMENTED OUT TEMPORARILY IN FAVOR OF ITS DUPLICATE
	 */
	 
	 /*
	function deleteFind($findId) {
		Log::getInstance()->log("deleteFind: $findId");

		$stmt = $this->db->prepare("delete from find where guid = :findId");
		$stmt->bindvalue(":findId", $findId);
		$stmt->execute();
		$this->deleteImages($findId);
		echo "Deletion of find with id = ".$findId." successful.";
		
		// Make an entry in find_history
		$stmt = $this->db->prepare(
			"insert into find_history (find_guid, action) VALUES
			(:find_guid, :action)"
		);
		$stmt->bindValue(":find_guid", $findId);
		$stmt->bindValue(":action", "delete");	
		$stmt->execute();
		
		$lastid = $this->db->lastInsertId();
		Log::getInstance()->log("Updated find_history, lastInsertId()=$lastid");
	}
	*/
	
/**
 * Creates a new expedition associated with the projectId.
 * @param $projectId
 * @return unknown_type
 */
	
	function addExpedition($projectId){
		$stmt = $this->db->prepare("INSERT INTO expedition ( project_id ) VALUES (:projectId)");
		$stmt->bindValue(":projectId", $projectId);
		$stmt->execute();
		return $this->db->lastInsertId();
	}
	
	/**
	 * Adds the expedion point data and associates it with the provided expedition id
	 * @param $expeditionId
	 * @param $latitude
	 * @param $longitude
	 * @param $altitude
	 * @param $swath
	 * @return unknown_type
	 */
	
	function addExpeditionPoint($expeditionId, $latitude, $longitude, $altitude, $swath, $time){
		Log::getInstance()->log("addExpeditionPoint, expId=$expeditionId, lat=$latitude,long=$longitude,alt=$altitude,swath=$swath,t=$time");
		$stmt = $this->db->prepare("INSERT INTO gps_sample ( expedition_id, latitude, longitude , altitude, swath, time, sample_time)" 
		."VALUES (:expeditionId, :latitude, :longitude, :altitude, :swath, :time, now() )");
		$stmt->bindValue(":expeditionId", $expeditionId);
		$stmt->bindValue(":latitude", $latitude);
		$stmt->bindValue(":longitude", $longitude);
		$stmt->bindValue(":altitude", $altitude);
		$stmt->bindValue(":swath", $swath);
		$stmt->bindValue(":time", $time);
		$stmt->execute();
		return $this->db->lastInsertId();
		//return $stmt->execute() > 0;
	}
	
	function getExpeditions($projectId){
		
		Log::getInstance()->log("getExpeditions, projectId = $projectId");
//		$this->createLog("I","getExpeditions"," $projectId");
		$stmt = $this->db->prepare("SELECT id, name, description, project_id FROM expedition WHERE project_id= :projectId ");
		$stmt->bindValue(":projectId", $projectId);
		$stmt->execute();
		$temp = $stmt->fetchAll(PDO::FETCH_ASSOC);
		return $temp;
	}
	/**
	 * Returns the most recent time associated with the expedition id from the gps_sample table.
	 * @param $expId
	 * @return $lastUpdate
	 */
	function getLastUpdate($expId){
		$query =  sprintf("SELECT MAX(sample_time) FROM gps_sample where expedition_id = %s",
		$expId);
		$stmt = $this->db->prepare($query);
		$stmt->execute();
		$lastUpdate = $stmt->fetch();
		return $lastUpdate[0];
	}
	
	function getLastFindTime($projId){
		$query =  sprintf("SELECT MAX(add_time) FROM find where project_id = %s",
		$projId);
		$stmt = $this->db->prepare($query);
		$stmt->execute();
		$lastUpdate = $stmt->fetch();
		return $lastUpdate[0];
	}
	/**
	 * Returns all points in an expedition beyond the specified time
	 * @param $expId
	 * @param $expTime
	 * @return unknown_type
	 */
	
	function getNewPoints($expId, $expTime){
		$query = sprintf("SELECT DISTINCT latitude, longitude, altitude, expedition_id 
		FROM `gps_sample` WHERE expedition_id = '%s' and sample_time > '%s'",
		$expId,
		$expTime);
		$stmt = $this->db->prepare($query);
		$stmt->execute();
		$newPoints = $stmt->fetchAll();
		return $newPoints;
	}
	
	/**
	 * Returns an associated array with the points that have the provided expedition id.
	 * @param $expeditionId
	 * @return unknown_type
	 */
	function getExpeditionPoints($expeditionId){
		$stmt = $this->db->prepare("
		SELECT DISTINCT
			latitude, longitude
		FROM gps_sample
		WHERE expedition_id= :expeditionId
                ORDER BY time
		"
		);
		$stmt->bindValue(":expeditionId", $expeditionId);
		$stmt->execute();
		$temp = $stmt->fetchAll(PDO::FETCH_ASSOC);
		return $temp;
	}
	
	/*
	 * Checks an array to prevent against SQL-injection.
	 * Then implodes the array using a comma as a delimter.
	 * @param $arrayOfInt an array
	 * @return $result a string containing each entry of $arrayOfInt
	 * 		casted to (int) and imploded using a comma as a delimiter  
	 */
	function checkAndImplode($arrayOfInt) {
		$result = "";
		foreach ($arrayOfInt as $entry) {
			$entry = (int) $entry;
			$result .= $entry . ",";
		}
		$result = rtrim($result, ",");
		return $result;
	}
	
	/* finds northernmost, southernmost, easternmost, and westernmost extremes
	 * among gps_samples from an array of expedition id's
	 * @param $expeditionIds an array of expedition id's
	 * @return an array containing the extremes in the order north-south-east-west
	 */
	function getExpExtremes($expeditions) {
		$id_array = array();
		foreach ($expeditions as $exp) {
			$id_array[] = $exp['id'];
		}
		$expeditionIdString = $this->checkAndImplode($id_array); 
		$stmt = $this->db->prepare ("
		SELECT max(latitude) north, min(latitude) south, max(longitude) east, min(longitude) west, count(*) num_rows 
		FROM gps_sample
		WHERE expedition_id
		IN ($expeditionIdString)
		");
		$stmt->execute();
		$row=$stmt->fetch(PDO::FETCH_ASSOC);
		extract($row);
		return array('north' => $north,'south' => $south,'east' => $east,'west' => $west);
	}
	
	/* finds northernmost, southernmost, easternmost, and westernmost extremes
	 * among the finds associated with a given project
	 * @param $projectId the id of the project of interest
	 * @return an array containing the extremes in the order north-south-east-west
	 */
	function getFindExtremes($projectId) {
		$stmt = $this->db->prepare ("
		SELECT max(latitude) north, min(latitude) south, max(longitude) east, min(longitude) west
		FROM find
		WHERE project_id=$projectId
		");
		$stmt->execute();
		$row=$stmt->fetch(PDO::FETCH_ASSOC);
		extract($row);
		return array('north' => $north,'south' => $south,'east' => $east, 'west'=>$west);
	}
	
	function getDualExtremes($exp_extremes,$find_extremes) {
		extract($exp_extremes);
		extract($find_extremes);
		$north = max(array($exp_extremes['north'],$find_extremes['north']));
		$south = min(array($exp_extremes['south'],$find_extremes['south']));
		$east = max(array($exp_extremes['east'],$find_extremes['east']));
		$west = min(array($exp_extremes['west'],$find_extremes['west']));
		return array('north' => $north,'south' => $south,'east' => $east, 'west'=>$west);
	}
	

	/*
	 * finds center of gps_samples from an array of extremes labeled north, south, east, and west as in the above two functions
	 * those functions are getExpExtremes (for expeditions) and getFindExtremes (for finds)
	 * @param $extremes an array of extremes
	 * @return $result an array of doubles the geographic center
	 */
	function getGeocenter($extremes) {
		extract($extremes);
		$lat = ($north+$south)/2.0;
		$long = ($east+$west)/2.0;
		$result = array('lat'=>$lat,'long'=>$long);
		return $result;
	}
	
	
	/**
	 * delete all finds
	 * @param unknown_type $projectId
	 */
	function deleteAllFinds($projectId) {
		$this->createLog("I","deleteAllFinds"," $projectId");

		$stmt = $this->db->prepare("delete from find where project_id = :projectId");
		$stmt->bindValue(":projectId", $projectId);
		$stmt->execute();
	}
	/**
	 * delete the given project
	 * @param unknown_type $id
	 * NOTE: This delete function does not delete a project per se, but rather flags it
	 *       as deleted rendering it hidden but keeping it in the databasee.
	 */
	function deleteProject($id) {
		Log::getInstance()->log("deleteProject: $id");

		$stmt = $this->db->prepare("update project set deleted=1 where id= :id");
		$stmt->bindValue(":id", $id);
		$stmt->execute();
	}
	
	/**
	 * delete the given find
	 * @param unknown_type $guid
	 * NOTE: This delete function does not delete a project per se, but rather flags it
	 *       as deleted rendering it hidden but keeping it in the databasee.
	 */
	 function deleteFind($guid) {
	 	Log::getInstance()->log("deleteFind: $guid");
	 		 	
	 	$stmt = $this->db->prepare("update find set deleted=1 where guid = :guid");
	 	$stmt->bindValue(":guid", $guid);
	 	$stmt->execute();
	 }
	 
	/**
	 * delete the image associated with the id
	 * @param unknown_type $findId
	 */
	function deleteImages($findId) {
		$stmt = $this->db->prepare("delete from photo where find_id = :findId");
		$stmt->bindValue(":findId", $findId);
		$stmt->execute();
		echo "Deletion of image with find_id = ".$findId." successful.";
	}
	/**
	 * delete all the videos associated with a find
	 * @param unknown_type $findId
	 */
	function deleteVideos($findId) {
		$stmt = $this->db->prepare("delete from video where find_id = :findId");
		$stmt->bindValue(":findId", $findId);
		$stmt->execute();
		echo "Deletion of video with find_id = ".$findId." successful.";
	}
	/**
	 * delete audio clips associated with a find
	 * @param unknown_type $findId
	 */
	function deleteAudioClips($findId) {
		$stmt = $this->db->prepare("delete from audio where find_id = :findId");
		$stmt->bindValue(":findId", $findId);
		$stmt->execute();
		echo "Deletion of audio clip with find_id = ".$findId." successful.";
	}
	
	/**
	 * Create  a new find
	 * @param unknown_type $guId
	 * @param unknown_type $projectId
	 * @param unknown_type $name
	 * @param unknown_type $description
	 * @param unknown_type $latitude
	 * @param unknown_type $longitude
	 * @param unknown_type $revision
	 */
	function createFind($auth_key, $imei, $guId, $projectId, $name, $description, $latitude, $longitude, $revision, $data) {
		Log::getInstance()->log("dao.createFind: $guId, $projectId, $name, $description, $latitude, $longitude, $revision, $data");

                // Note use of 'on duplicate key update'
                $stmt = $this->db->prepare(
                        "insert into find (imei, guid, project_id, name, description,
                        latitude, longitude, add_time, modify_time, revision,auth_key) VALUES
                        (:imei, :guid, :projectId, :name, :description, :latitude, :longitude ,now(), now(), :revision, :auth_key)
                        on duplicate key update name = :name, description = :description, modify_time = now(), revision = :revision"
                );

		$stmt->bindValue(":imei", $imei);
		$stmt->bindValue(":guid", $guId);
		$stmt->bindValue(":projectId", $projectId);
		$stmt->bindValue(":name", $name);
		$stmt->bindValue(":description", $description);
		$stmt->bindValue(":latitude", $latitude);
		$stmt->bindValue(":longitude", $longitude);
		$stmt->bindValue(":revision", $revision);
		$stmt->bindValue(":auth_key", $auth_key);
		$stmt->execute(); 
		
		$findid = $this->db->lastInsertId();
		
		$stmt = $this->db->prepare(
				"insert into find_extension(find_id, data) VALUES (:find_id, :data)");
				
		$stmt->bindValue(":find_id", $findid);
		$stmt->bindValue(":data", $data);
		$stmt->execute();
		
		Log::getInstance()->log("createFind"." lastInsertId()=$findid"." extended data=$data");
		
		// Make an entry in find_history
		$stmt = $this->db->prepare(
			"insert into find_history (find_guid, action, imei, auth_key) VALUES
			(:find_guid, :action, :imei, :auth_key)"
		);
		$stmt->bindValue(":find_guid", $guId);
		$stmt->bindValue(":action", "create");	
		$stmt->bindValue(":imei", $imei);
		$stmt->bindValue(":auth_key", $auth_key);
		$stmt->execute();
		
		$lastid = $this->db->lastInsertId();
		$this->createLog("I","createFind","Updated find_history, created lastInsertId()=$lastid");

//		return $findid; //get the rowid where it's inserted so that the client can sync.. @todo update in API
		return "True Created $guId in row=$findid";  
	}
	
	
	/**
	 * Update information about a find
	 * @param unknown_type $guId -- globally unique ID
	 * @param unknown_type $name
	 * @param unknown_type $description
	 * @param unknown_type $revision
	 */
	function updateFind($auth_key,$imei, $guId, $projectId, $name, $description, $revision, $data, $latitude, $longitude) {
		Log::getInstance()->log("updateFind: $auth_key, $imei, $guId, $projectId, $name, $description, $revision, $data, $latitude, $longitude");
		$stmt = $this->db->prepare("update find set name = :name, description = :description, 
			revision = :revision, modify_time = NOW(), latitude = :latitude, longitude = :longitude where guid = :guid AND project_id = :projectId");
		
		$stmt->bindValue(":name", $name);
		$stmt->bindValue(":description", $description);
		$stmt->bindValue(":revision", $revision);
		$stmt->bindValue(":guid", $guId);
		$stmt->bindValue(":projectId", $projectId);
		$stmt->bindValue(":latitude", $latitude);
		$stmt->bindValue(":longitude", $longitude);
		$stmt->execute();
		$this->createLog("I","updateFind","Updated Find= $guId");
		Log::getInstance()->log("getFind: id = $id");
		
		// Get this Find's id for query to extended data
		$stmt = $this->db->prepare("select id from find where guid = :guid");
		$stmt->bindValue(":guid", $guId);
		$stmt->execute();
		$idResult = $stmt->fetchAll(PDO::FETCH_ASSOC);
		$id = $idResult[0]["id"];
		Log::getInstance()->log("updateFind: id = $id");

		// Update the extended data
		$stmt = $this->db->prepare(
				"update find_extension set data = :data where find_id = :find_id");
				
		$stmt->bindValue(":find_id", $id);
		$stmt->bindValue(":data", $data);
		$stmt->execute();
		Log::getInstance()->log("updateFind: updated extended data for find_id = $id");

		// Make an entry in find_history
		$stmt = $this->db->prepare(
			"insert into find_history (find_guid, action, imei, auth_key) VALUES (:find_guid, :action, :imei, :auth_key)"
		);
		$stmt->bindValue(":find_guid", $guId);
		$stmt->bindValue(":action", "update");	
		$stmt->bindValue(":imei", $imei);	
		$stmt->bindValue(":auth_key", $auth_key);
		$stmt->execute();
		Log::getInstance()->log("Updated find_history, updated Find $guId $imei");
		return "True Updated $guId on server";
	}
	
	/**
	 * Add a picture to the find
	 * @param unknown_type $id
	 * @param unknown_type $findId
	 * @param unknown_type $mimeType
	 * @param unknown_type $dataFull
	 * @param unknown_type $dataThumb
	 */
	function addPictureToFind($imei, $guid, $identifier, $project_id,  $mime_type, $timestamp, $dataFull, $dataThumb, $authKey) {
		$this->createLog("I","addPictureToFind"," $imei, $guid, $identifier, $mimeType");

		$stmt = $this->db->prepare(
			"insert into photo (imei, guid, identifier, project_id,  mime_type, timestamp, data_full, data_thumb, auth_key)
			           VALUES (:imei, :guid, :identifier, :project_id, :mime_type, :timestamp, :dataFull, :dataThumb, :authKey)"
		);
		$stmt->bindValue(":imei",$imei);
		$stmt->bindValue(":guid",$guid);
		$stmt->bindValue(":identifier",$identifier);
		$stmt->bindValue(":project_id",$project_id);
		$stmt->bindValue(":mime_type",$mime_type);
		$stmt->bindValue(":timestamp",$timestamp);
		$stmt->bindValue(":dataFull",$dataFull);
		$stmt->bindValue(":dataThumb",$dataThumb);
		$stmt->bindValue(":authKey",$authKey);
		$stmt->execute();
		$this->createLog("I","addPictureToFind"," $imei, $guid, $identifier, $mimeType");
		
		$lastid = $this->db->lastInsertId();
		$this->createLog("I","addPictureToFind","Updated photos for Find $guid, created record lastInsertId()=$lastid");
		return "True Created photo record $guId in row=$lastid";  
		//return $stmt->fetch(PDO::FETCH_ASSOC);
	}
	
	/**
	 * Add video to the find
	 * @param unknown_type $id
	 * @param unknown_type $findId
	 * @param unknown_type $mimeType
	 * @param unknown_type $dataPath
	 */
	function addVideoToFind($id, $findId, $mimeType, $dataPath) {
		$stmt = $this->db->prepare(
			"insert into video (id, find_id, mime_type, data_path)
			VALUES (:id, :findId, :mimeType, :dataPath)"
		);
		$stmt->bindValue(":id", $id);
		$stmt->bindValue(":findId", $findId);
		$stmt->bindValue(":mimeType", $mimeType);
		$stmt->bindValue(":dataPath", $dataPath);
		
		$stmt->execute();
		return $stmt->fetch(PDO::FETCH_ASSOC);
	}
	/**
	 * Add audio clip to the find
	 * @param unknown_type $id
	 * @param unknown_type $findId
	 * @param unknown_type $mimeType
	 * @param unknown_type $dataPath
	 */
	function addAudioClipToFind($id, $findId, $mimeType, $dataPath) {
		$stmt = $this->db->prepare(
			"insert into audio (id, find_id, mime_type, data_path)
			VALUES (:id, :findId, :mimeType, :dataPath)"
		);
		$stmt->bindValue(":id", $id);
		$stmt->bindValue(":findId", $findId);
		$stmt->bindValue(":mimeType", $mimeType);
		$stmt->bindValue(":dataPath", $dataPath);
		
		$stmt->execute();
		return $stmt->fetch(PDO::FETCH_ASSOC);
	}
	/**
	 * deletes picture from the find
	 * @param unknown_type $id
	 */
	function deletePictureFromFind($id) {
		$stmt = $this->db->prepare(
			"delete from photo where id = :id"
		);
		$stmt->bindValue(":id", $id);
		$stmt.execute();
	}
	/**
	 * delete video from the find
	 * @param $id
	 */
	function deleteVideoFromFind($id) {
		$stmt = $this->db->prepare(
			"select data_path from video where id = :id");
		$stmt->bindValue(":id", $id);
		$stmt.execute();
		$video = $stmt->fetch(PDO::FETCH_ASSOC);
		$video_path = $video['data_path'];
		$fh = fopen("uploads/$video_path", 'w') or die("can't open file");
		fclose($fh);
		unlink($video_path);
		$stmt = $this->db->prepare(
			"delete from video where id = :id"
		);
		$stmt->bindValue(":id", $id);
		$stmt.execute();
	}
	/**
	 * delete audio clip from a  find
	 * @param unknown_type $id
	 */
	function deleteAudioClipFromFind($id) {+
		$stmt = $this->db->prepare(
			"select data_path from audio where id = :id");
		$stmt->bindValue(":id", $id);
		$stmt.execute();
		$audio = $stmt->fetch(PDO::FETCH_ASSOC);
		$audio_path = $audio['data_path'];
		$fh = fopen("uploads/$audio_path", 'w') or die("[Error on server]Can't open file");
		fclose($fh);
		unlink($audio_path);
		$stmt = $this->db->prepare(
			"delete from audio where id = :id"
		);
		$stmt->bindValue(":id", $id);
		$stmt.execute();
	}
	/**
	 * register a new user
	 * @param $newUser
	 */
	function registerUser($newUser) {
		$this->createLog("I","registerUser"," $newUser");
		list($email, $firstName, $lastName, $password) = $newUser;
		
		$stmt = $this->db->prepare(
			"SELECT id FROM user WHERE email = :email"
		);
		
		$stmt->bindValue(":email", $email);
		$stmt->execute();
		
		if($stmt->fetch())
			return REGISTRATION_EMAILEXISTS;
		
		$stmt = $this->db->prepare(
			"INSERT INTO user (first_name, last_name, email, password, create_time)
			 VALUES (:firstName, :lastName, :email, SHA1(:password), now())"
		);
		$stmt->bindValue(":firstName", $firstName);
		$stmt->bindValue(":lastName", $lastName);
		$stmt->bindValue(":email", $email);
		$stmt->bindValue(":password", $password);
		$stmt->execute();
//		return true;
		return array($this->db->lastInsertId());
	}
	/**
	 * get all the devices the user has registered
	 * @param unknown_type $userId
	 */
	function getDevicesByUser($userId) {
		$this->createLog("I","getDevicesByUser","$userId");
		$stmt = $this->db->prepare(
			"SELECT imei, name, auth_key, add_time
			 FROM device
			 WHERE user_id = :userId
			 AND status = 'ok'"
		);
		$stmt->bindValue(":userId", $userId);
		$stmt->execute();
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
	}
	/**
	 * get devices by the key given
	 * @param unknown_type $authKey
	 */
	function getDeviceByAuthKey($authKey) {
//		$this->createLog("I","getDeviceByAuthKey: $authKey");
		$stmt = $this->db->prepare(
			"SELECT imei, name, user_id, add_time, status
			 FROM device
			 WHERE auth_key = :authKey
			 LIMIT 1"
		); $stmt->bindValue(":authKey", $authKey);
		$stmt->execute();
		return $stmt->fetch(PDO::FETCH_ASSOC);
	}
	/**
	 * registration pending verification
	 * @param unknown_type $userId
	 * @param unknown_type $authKey
	 */
	function registerDevicePending($userId, $authKey) {
//		print_r(array($userId, $authKey));
		$this->createLog("I","registerDevicePending","$userId, $authKey");
		if(!$userId || !$authKey) return false;
		$stmt = $this->db->prepare(
			"INSERT INTO device (user_id, auth_key, add_time)
			 VALUES (:userId, :authKey, now())"
		);
		print_r(mysql_error());

		$stmt->bindValue(":userId", $userId);
		$stmt->bindValue(":authKey", $authKey);
		$stmt->execute();
		
		return true;
	}
	/**
	 * confirm registration 
	 * @param unknown_type $authKey
	 * @param unknown_type $imei
	 * @param unknown_type $name
	 */
	function confirmDevice($authKey, $imei, $name) {
		$this->createLog("I","confirmDevice", $authKey." ".$imei." ".$name);

		$stmt = $this->db->prepare(
			"SELECT auth_key FROM device WHERE imei = :imei"
		);
		$stmt->bindValue(":imei", $imei);
		$stmt->execute();
		
		if($existingDevice = $stmt->fetch(PDO::FETCH_ASSOC)) {
			$res = mysql_query("select user_id from device where auth_key = '$authKey'") or die(mysql_error());
			list($userId) = mysql_fetch_array($res, MYSQL_NUM);
			
			mysql_query(
				"DELETE FROM device WHERE auth_key = '$authKey'"
			);
			mysql_query(
				"UPDATE device SET auth_key = '$authKey', status = 'ok', user_id = '$userId' WHERE imei = '$imei'"
			);
			
			return true;
			//$stmt->bindValue(":authKey", $authKey);
			//$stmt->bindValue(":imei", $imei);
			//return $stmt->execute();
			/*
			$stmt = $this->db->prepare(
				"SELECT name FROM device WHERE imei = :imei"
			);
			$stmt->bindValue(":imei", $imei);
			$stmt->execute();
			
			list($name) = $stmt->fetch(PDO::FETCH_ASSOC);
			*/
		}
		
		$stmt = $this->db->prepare(
			"UPDATE device SET
			 imei = :imei,
			 name = :name,
			 status = 'ok'
			 WHERE auth_key = :authKey"
		);
		$stmt->bindValue(":imei", $imei);
		$stmt->bindValue(":name", $name);
		$stmt->bindValue(":authKey", $authKey);
		$result = $stmt->execute();
		return $result;
	}
	/**
	 * add a device to the sandbox
	 * @param unknown_type $authKey
	 * @param unknown_type $imei
	 */
	function addSandboxDevice($authKey, $imei) {
		$this->createLog("I","addSandboxDevice ",$authKey . " " . $imei);
		$stmt = $this->db->prepare("delete from device where imei = :imei");
		$stmt->bindValue(":imei", $imei);
		$stmt->execute();
		
		$stmt = $this->db->prepare(
			"INSERT INTO device (imei, user_id, auth_key, add_time, status)
			 VALUES (:imei, 0, :authKey, now(), 'ok')"
		);
		$stmt->bindValue(":imei", $imei);
		$stmt->bindValue(":authKey", $authKey);
		$stmt->execute();
		return true;
	}
	/**
	 * change/set the nickname of the device 
	 * @param unknown_type $imei
	 * @param unknown_type $name
	 */
	function changeDeviceNickname($imei, $name) {
		$stmt = $this->db->prepare(
			"UPDATE device SET name = :name WHERE imei = :imei"
		);
		$stmt->bindValue(":name", $name);
		$stmt->bindValue(":imei", $imei);
		return $stmt->execute();
	}
	/**
	 * remove a device from the database
	 * @param unknown_type $imei
	 */
	function removeDevice($imei) {
		$stmt = $this->db->prepare(
			"DELETE FROM device WHERE imei = :imei"
		);
		$stmt->bindValue(":imei", $imei);
		return $stmt->execute();
	}
	/**
	 * remove all the devices that didn't get verified
	 */
	function purgePendingDevices() {
		$stmt = $this->db->prepare("DELETE FROM device WHERE imei IS NULL");
		$stmt->execute();
	}
	/**
	 * search for finds
	 * @param $search_value
	 * @param $project_id
	 */
	function searchFinds($search_value, $project_id){
		$stmt = $this->db->prepare(
			"SELECT id, name, description
			FROM find
			WHERE project_id = :project_id AND name LIKE CONCAT('%', :search_value, '%')"
			) or print_r($this->db->errorInfo()) && die();

		$stmt->bindValue(":search_value", $search_value);
		$stmt->bindValue(":project_id", $project_id);
		$stmt->execute();
		$available_values = array();
		$temp = $stmt->fetchAll(PDO::FETCH_ASSOC);
		
		foreach($temp as $value){
			$available_values[] = $value;
		}
		return $available_values;
	}
	/**
	 * generic execute command
	 * @param $command_value
	 */
	function execCommand($command_value){
		if ($command_value == "create_sample_text") {
			$file = "test/HelloWorld.txt";
			$handler = fopen($file, 'w') or die("can't open file");
			$data = "This is a sample text created in order to demonstrate the functionality of the command line";
			fwrite($handler, $data);
			fclose($handler);
		}
	}

	function addInstance($project_id, $name, $description, $sync_on, $auth_key){
		$stmt = $this->db->prepare("INSERT INTO instance (project_id, name, description, sync_on, auth_key) VALUES (:project_id, :name, :description, :sync_on, :auth_key)") or print_r($this->db->errorInfo()) && die();
		$stmt->bindValue(":project_id", $project_id);
		$stmt->bindValue(":name",$name);
		$stmt->bindValue(":description",$description);
                $stmt->bindValue(":sync_on", $sync_on);
		$stmt->bindValue(":auth_key", $auth_key);
		$stmt->execute();

		return true;	
	}
	

	function getInstancesForProject ($project_id){
		$stmt = $this->db->prepare(
			"SELECT id, name, description, project_id, sync_on, auth_key
			FROM instance
			WHERE project_id = :project_id"
			) or print_r($this->db->errorInfo()) && die();
		$stmt->bindValue(":project_id", $project_id);
		$stmt->execute();
		$available_values = array();
		$temp = $stmt->fetchAll(PDO::FETCH_ASSOC);
		
		foreach($temp as $value){
			$available_values[] = $value;
		}
		return $available_values;

	}
	function projectExists ($project_id){
		$project= $this->getProject($project_id);
//		var_dump($project);
		if ($project["deleted"]==0){
			return "true";
		}else {
			return "false";
		}
	}
	
	/* Gets project name associated with project id
	 * @param the project id
	 * @return the project name
	 */
	function getProjectName ($project_id) {
		$stmt = $this->db->prepare(
			"SELECT name
			FROM project
			WHERE id = :project_id
			");
		$stmt->bindValue(":project_id", $project_id);
		$stmt->execute();
		$row=$stmt->fetch(PDO::FETCH_ASSOC);
		return "".$row['name'];
	}
	
	/* Gets the project description associated with project id
	 * @param the project id
	 * @return the project description
	 */
	function getProjectDescription ($project_id) {
		$stmt = $this->db->prepare(
			"SELECT description
			FROM project
			WHERE id = :project_id
			");
		$stmt->bindValue(":project_id", $project_id);
		$stmt->execute();
		$row=$stmt->fetch(PDO::FETCH_ASSOC);
		return "".$row['description'];
	}
	
	/* Exports project with given project id to .csv file
	 * NOTE: date is modified to be easily parsed by Microsoft Excel
	 * @param the project id
	 * @return the string to be parsed as a .csv
	 */
	function exportProject ($project_id){
		$project_name = $this->getProjectName($project_id);
		$project_description = $this->getProjectDescription($project_id);
		$stmt = $this->db->prepare(
			"SELECT description, name, add_time, latitude, longitude
			FROM find
			WHERE project_id=:project_id			
			AND deleted=0
			");
		$stmt->bindValue(":project_id", $project_id);
		$stmt->execute();
		$csvwriter = "";
		$csvwriter = "Project Name: {$project_name},,,,\n";
		$csvwriter .= "Project Description: {$project_description},,,,\n\n";
		$csvwriter .= "NAME,DESCRIPTION,DATE ADDED,LATITUDE,LONGITUDE\n";
		while ($row=$stmt->fetch(PDO::FETCH_ASSOC))
			{
			extract($row);
			$new_time = convertDate(strtotime($add_time), "excel");
			// removes commas to allow for correct delimiting
			$new_description = str_replace(",","",$description);
			$csvwriter .= "$name,$new_description,$new_time,$latitude,$longitude";
			$csvwriter .= "\n";
			}
		return $csvwriter;
	}
	
	/*
	 * replaces spaces in a string with underscores
	 * @param the id of the project whose name should be fixed
	 * @param the new string without spaces
	 */
	function formatProjectName($project_id) {
		$project_name = $this->getProjectName($project_id);
		$arr_name = explode(" ",$project_name);
		$new_name = "";
		foreach ($arr_name as $word) {
			$new_name .= $word . "_";
		}
		$new_name = rtrim($new_name, "_");
		return $new_name;
	}
		
}

?>
