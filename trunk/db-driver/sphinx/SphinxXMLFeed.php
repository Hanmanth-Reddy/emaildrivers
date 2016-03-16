<?php
/*
  *  SphinxXMLFeed - efficiently generate XML for Sphinx's xmlpipe2 data adapter
  *  (c) 2009 Jetpack LLC http://jetpackweb.com
  */
class SphinxXMLFeed extends XMLWriter
{
  private $fields = array();
  private $attributes = array();
  protected $kill_list = array();
  
  public function __construct($options = array())
  {
    $defaults = array(
      'indent' => false,
    );
    $options = array_merge($defaults, $options);
 
    // Store the xml tree in memory
    $this->openMemory();
 
    if($options['indent']) {
      $this->setIndent(true);
    }
  }
 
  public function setFields($fields) {
    $this->fields = $fields;
  }
 
  public function setAttributes($attributes) {
    $this->attributes = $attributes;
  }

  public function addKillList($kill_list){
        $this->kill_list = $kill_list;
  }
	
  public function addDocument($doc) {
    $this->startElement('sphinx:document');
    $this->writeAttribute('id', $doc['id']);
 
    foreach($doc as $key => $value) {
      // Skip the id key since that is an element attribute
      if($key == 'id') continue;
      if($key=="profile_data" || $key=="resume_data" || $key=="notes")
      {
              $this->startElement($key);
              $this->writeCData($value);
              $this->endElement();
      }else
      {
              $this->startElement($key);
              $this->text($value);
              $this->endElement();
      }
    }
 
    $this->endElement();
    print $this->outputMemory();
  }
 
  public function beginOutput() {
 
    $this->startDocument('1.0', 'UTF-8');
    //$this->startDocument('1.0', 'iso-8859-1');
    $this->startElement('sphinx:docset');
    $this->startElement('sphinx:schema');
 
    // add fields to the schema
    foreach($this->fields as $field) {
      $this->startElement('sphinx:field');
      $this->writeAttribute('name', $field);
	  $this->writeAttribute('attr', 'string');  
      $this->endElement();
    }
 
    // add attributes to the schema
    foreach($this->attributes as $attributes) {
      $this->startElement('sphinx:attr');
      foreach($attributes as $key => $value) {
        $this->writeAttribute($key, $value);
      }
      $this->endElement();
    }
 
    // end sphinx:schema
    $this->endElement();
    print $this->outputMemory();
  }
 
  public function endOutput()
  {
	// add kill list
	if (!empty($this->kill_list)){
		$this->startElement('sphinx:killlist');
			foreach ($this->kill_list as $id)
			{
				$this->writeElement("id", $id);
			}
		$this->endElement();
	}
	// end sphinx:docset
	$this->endElement();
	print $this->outputMemory();
  }
}

function pipesBuffering($filearray)
{
	$descriptorspec = array(
		0 => array("pipe", "r"),  // stdin is a pipe that the child will read from
		1 => array("pipe", "w"),  // stdout is a pipe that the child will write to
		2 => array("file", "/tmp/error-output.txt", "a") // stderr is a file to write to
	);
	$process = proc_open("tika -t -", $descriptorspec, $pipes);
	if (is_resource($process)) {
		// $pipes now looks like this:
		// 0 => writeable handle connected to child stdin
		// 1 => readable handle connected to child stdout
		// Any error output will be appended to /tmp/error-output.txt

		fwrite($pipes[0],$filearray['content']);
		fclose($pipes[0]);

		// THIS WILL NOT DISPLAY IN BROWSER 
		// OUTPUT BUFFERING SHOULD BE ENABLED IF YOU WANT TO SEE THIS IN THE BROWSER
		$pushData = '';
		while(!feof($pipes[1])) {
			$pushData .= fgets($pipes[1], 1024);
		}
		fclose($pipes[1]);
		// It is important that you close any pipes before calling
		// proc_close in order to avoid a deadlock
		$status=proc_close($process);
		file_put_contents("/tmp/error-output.txt","Doc ID : ".$filearray['doc_id']." Extract Status : ".$status."\n",FILE_APPEND);
	}
	return $pushData;
}
?>
