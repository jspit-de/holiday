<?php
/**
*
* @license http://opensource.org/licenses/MIT
* @version 1.1
* @date: 2018-06-25
* Copyright © 2018, Peter Junk (alias jspit). All Rights Reserved. 
*/

class icsEventReader 
{
  private $content = "";
  private $contentPos = 0;

  /**
   * Creates a new instance
   *
   * @param string $FileName   
   * @param string $isoCountry  the file open mode 
   */

  public function __construct($url = null, $isoCountry = "DE")
  {
    if(empty($url)) {
      $url = "https://www.officeholidays.com/ics/ics_country_code.php"; 
    }
    $query = http_build_query(array(
      'iso' => $isoCountry,
    )); 
    $this->content = file_get_contents($url."?".$query);
    $codePage = mb_detect_encoding($this->content,"CP1252,ISO-8859-1,UTF-8", true);
    if($codePage !== false AND $codePage != "UTF-8") {
      $this->content = mb_convert_encoding(
        html_entity_decode($this->content, ENT_COMPAT, $codePage),
        "UTF-8", 
        $codePage
      );
    }
  }
  
 /*
  * @param int year default null
  * @return object(->description, location, date)
  * 
    (object)(array(
     'date' => "2008-01-01",
     'location' => "Germany",
     'description' => "New Years Day",
     ))
  */  
  public function getNextEvent($year = null, $publicHoliday = true, $regionalHoliday = true){
    
    do {
      $startEvent = false;
      $event = (object)null;
      while($line = $this->getLine()) {
        if($line == "BEGIN:VEVENT") $startEvent = true;
        if($startEvent) {
          if($line == "END:VEVENT") break;
          
          if(stripos($line,"SUMMARY") === 0) {
            $event->description = trim(preg_replace('~^.+:~','',$line));
          }
          if(stripos($line,"LOCATION") === 0) $event->location = substr($line,9,100);
          if(stripos($line,"DTSTART") !== false) {
            $part = explode(":",$line);
            $date = date_create($part[1]);
            $event->date = $date->format("Y-m-d");
          }
        }
      }
      if(!$startEvent) return false;
    } while(
       ($year !== null AND $year != substr($event->date,0,4))
        OR 
       ($publicHoliday AND preg_match('~Not.+public~i',$event->description) == 1)
        OR
        (!$regionalHoliday AND preg_match('~\(Regional\)~i',$event->description) == 1)
      );
    
    return $event;
  }
  
  public function getLine(){
    $newPos = strpos($this->content, "\r\n", $this->contentPos);
    if($newPos === false) return false;
    $line = substr($this->content, $this->contentPos, $newPos-$this->contentPos);
    $this->contentPos = $newPos + 2;
    return $line;
  }

  public function reset(){
    $this->contentPos = 0;
  }  
  
}
