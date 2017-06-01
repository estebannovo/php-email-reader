<?php
class Email_reader {
	// imap server connection
	public $conn;

	// inbox storage and inbox message count
	private $inbox;
	private $msg_cnt;

	// email login credentials
	private $server;
	private $user;
	private $pass;
	private $port;
	private $service_type;
	private $default_inbox;
	private $useCert = '';

	// connect to the server
	function __construct($server, $port, $user, $pass, $service_type='imap', $use_ssl=true, $cert='', $default_inbox="INBOX") {
		// Initializing  de attributes
		$this->server = $server;
		$this->port = $port;
		$this->user = $user;
		$this->pass = $pass;
		$this->service_type = $service_type;

		if($use_ssl){
			$this->use_ssl = '/ssl';
		}

		if(!empty($cert)){
			$this->useCert = "/$cert";
		}

		$this->default_inbox = $default_inbox;

		//create connection to server
		$this->connect();
	}

	// close the server connection
	function close() {
		$this->inbox = array();
		$this->msg_cnt = 0;

		imap_close($this->conn);
	}

	// open the server connection
	// the imap_open function parameters will need to be changed for the particular server
	// these are laid out to connect to a Dreamhost IMAP server
	function connect() {
		$this->conn = imap_open('{'.$this->server.':'.$this->port.'/'.$this->service_type.''.$this->use_ssl.''.$this->useCert.'}'.$this->default_inbox, $this->user, $this->pass);
		echo (new DateTime)->format("YmdHis")." - Connected!\n";
	}

	function list_mailbox(){
		$list_mailbox = imap_list($this->conn, '{'.$this->server.':'.$this->port.'/'.$this->service_type.''.$this->use_ssl.''.$this->useCert.'}INBOX', "*");
		$arr_mailboxes = array();
		if (is_array($list_mailbox)) {
		    foreach ($list_mailbox as $mailbox) {
		    	array_push($arr_mailboxes, imap_utf7_decode($mailbox));
		    }
		}
		return $arr_mailboxes;
	}

	// Move the message to a new mailbox
	function move($msg_index, $mailbox='INBOX.Trash') {
		$mailboxes = $this->list_mailbox();
		//Check direcotry exists
		if(!in_array('{'.$this->server.':'.$this->port.'/'.$this->service_type.''.$this->use_ssl.''.$this->useCert.'}'.$mailbox, $mailboxes)){
			imap_createmailbox($this->conn, imap_utf7_encode('{'.$this->server.':'.$this->port.'/'.$this->service_type.''.$this->use_ssl.''.$this->useCert.'}'.$mailbox));
		}

		// move on server
		imap_mail_move($this->conn, $msg_index, $mailbox, CP_UID);
		imap_expunge($this->conn);
		// re-read the inbox
		//$this->inbox();
	}

	// get a specific message (1 = first email, 2 = second email, etc.)
	function get($msg_index=NULL) {
		if (count($this->inbox) <= 0) {
			return array();
		}
		elseif ( ! is_null($msg_index) && isset($this->inbox[$msg_index])) {
			return $this->inbox[$msg_index];
		}

		return $this->inbox[0];
	}


	//Search message from email account
	function sfrom($array_needle){
		$email_ids = array();
		while (list(,$needle)=each($array_needle)) {
			$aux = imap_search($this->conn,'FROM "'.$needle.'"', SE_UID);
			if($aux){
				$email_ids = array_merge($email_ids, (array)$aux);
			}
		}
		return $email_ids;
	}

	// Search mails has attachments
	function sfrom_hasAttachment($array_needle){
		$email_ids = $this->sfrom($array_needle);
		$in = $this->getEmailStructure($email_ids);		
		return $this->hasAttachment($in);
	}

	// Return the mail structure 
	private function getEmailStructure($email_ids){
		$in = array();
		while (list(,$UIDs)=each($email_ids)) {
			$in[] = array(
					'index'     => $UIDs,
					'structure' => imap_fetchstructure($this->conn, $UIDs, FT_UID)
				);
		}
		return $in;
	}

	// Validate of one e-mail has attachment
	private function hasAttachment($arr_emails_structure){
		$filtered = array();
		foreach ($arr_emails_structure as $email) {
			if(isset($email['structure']->parts) && count($email['structure']->parts)){
				// loop through all attachments
				for ($i = 0; $i < count($email['structure']->parts); $i++) {
					// if this attachment has ifparameters, then proceed as above
					if ($email['structure']->parts[$i]->ifparameters) {
						foreach ($email['structure']->parts[$i]->parameters as $object) {							
							if (strtolower($object->attribute) == 'name') {
								array_push($filtered, $email['index']);
							}
						}
					}
										
				}
			}
		}
		return $filtered;
	}

	// Downloas attachments from mail lists and save attachment in a directory
	public function download_mail_attachments_from($array_needle, $path_to_save_attachments, $move_mails=false, $extensions_to_process='xlsx ',$inbox_to_move='INBOX.Trash'){
		$email_ids = $this->sfrom($array_needle);
		$in = $this->getEmailStructure($email_ids);
		
		$attachments = array();
		$new_attachments = array();
		foreach ($in as $key => $email) {
			if(isset($email['structure']->parts) && count($email['structure']->parts)){
				// loop through all attachments
				for ($i = 0; $i < count($email['structure']->parts); $i++) {
					// if this attachment has ifparameters, then proceed as above
					if ($email['structure']->parts[$i]->ifparameters) {
						foreach ($email['structure']->parts[$i]->parameters as $object) {							
							if (strtolower($object->attribute) == 'name') {								
								$name = iconv_mime_decode ($object->value, 0, "ISO-8859-1");
								$name = (!mb_check_encoding($name, 'UTF-8'))?utf8_encode($name):$name;
								$attachments[] = array(
									'name'=> $name,
									'index' => $email['index'],
									'position' => $i,
									'encoding' => $email['structure']->parts[$i]->encoding
								);
							}
						}
					}					
				}
			}
		}

		foreach ($attachments as $att) {
			// get the content of the attachment
			$att['attachment'] = imap_fetchbody($this->conn, $att['index'], $att['position']+1, FT_UID);
			// check if this is base64 encoding
			if ($att['encoding']== 3) { // 3 = BASE64
				$att['attachment'] = base64_decode($att['attachment']);
			}
			// otherwise, check if this is "quoted-printable" format
			elseif ($att['encoding'] == 4) { // 4 = QUOTED-PRINTABLE
				$att['attachment'] = quoted_printable_decode($att['attachment']);
			}						
			$new_attachments[] = $att;
		}

		//Save attachments in path
		foreach ($new_attachments as $a) {
			$filename = $a['name'];
			$extension = pathinfo("$path_to_save_attachments/$a[index]_$filename", PATHINFO_EXTENSION);
	        if(in_array($extension, $extensions_to_process)){
		        if(file_put_contents("$path_to_save_attachments/$a[index]_$filename", $a['attachment']) === FALSE){
		        	echo (new DateTime)->format("YmdHis"). " - ERROR: Error creating file $path_to_save_attachments/$a[index]_$filename\n";
		        }else{
	        		echo (new DateTime)->format("YmdHis")." - Filename $path_to_save_attachments/$a[index]_$filename filename created successfully\n";		        	
		        }
	        }
	        if($move_mails){
		        // Move attachment to inbox
		        $this->move($a['index'], $inbox_to_move);
	        }
		}
	}

	// read the inbox
	function inbox() {
		$this->msg_cnt = imap_num_msg($this->conn);
		$in = array();
		for($i = 1; $i <= $this->msg_cnt; $i++) {
			$in[] = array(
				'index'     => $i,
				'header'    => imap_headerinfo($this->conn, $i),
				'body'      => imap_body($this->conn, $i),
				'structure' => imap_fetchstructure($this->conn, $i)
			);
		}

		$this->inbox = $in;
	}
}
?>
