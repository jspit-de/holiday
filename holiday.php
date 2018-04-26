<?php
/**
.---------------------------------------------------------------------------.
|  Software: Holiday - PHP class                                            |
|   Version: 1.21                                                           |
|      Date: 2018-04-26                                                     |
|      Site:                                                                |
| ------------------------------------------------------------------------- |
| Copyright © 2018, Peter Junk alias jspit All Rights Reserved.             |
'---------------------------------------------------------------------------'
*/

class holiday
{
  public $pdo;
  protected $language = "de-DE";
  protected $region;
  protected $config;

  /*
   * Constructs the class instance
   * @param $filterRegion string: Country/Region ISO 3361 Alpha2 ('DE','DE-BY'..) 
   * @param filename filename for SQLite, default: holiday.sqlite
   */
  public function __construct($filterRegion = "", $sqliteFile = null) {
    //verify filter
    $filter = strtoupper($filterRegion);
    if(!preg_match('/^[A-Z]{2,3}(-[A-Z0-9]{1,8}){0,3}$/', $filter)) {
      throw new Exception("Error new Class ".__CLASS__.": filterRegion is not like ISO3361");  
    }
        
    $options = array(
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
    );
    if(! is_string($sqliteFile) OR $sqliteFile == "") {
      $sqliteFile = __DIR__ . "/holiday.sqlite";
    }
    if(!file_exists($sqliteFile)){
      throw new Exception("Error new Class ".__CLASS__.": SQLite File '$sqliteFile' not found");
    }
    $this->pdo = new PDO('sqlite:'.$sqliteFile,null,null,$options);
    if(! $this->createConfig($filterRegion)) {
      throw new Exception("Error new Class ".__CLASS__.": faulty SQLite-DB '$sqliteFile'");  
    }
    
  }
  
  /*
   * return a new class instance
   * @param $filterRegion string: Country/Region ISO 3361 Alpha2 ('DE','DE-BY'..) 
   * @param filename filename for SQLite, default: holiday.sqlite
   */
  public static function create($filterRegion = "", $sqliteFile = null) {
    return new static($filterRegion,$sqliteFile);    
  }
  
  
 /*
  * set Default Language
  * @param $language string p.E. "de-DE", "en-GB" 
  */
  public function setLanguage($language = "de-DE") {
    $this->language = $language;
    return $this;
  }

 /*
  * get Default Language
  */
  public function getLanguage() {
    return $this->language;
  }

 /*
  * set region
  * @param $filterRegion string: Country/Region ISO 3361 Alpha2 ('DE','DE-BY'..) 
  */
  public function setRegion($filterRegion) {
    if($this->createConfig($filterRegion)) {
      return $this;
    }
    return false;
  }
  
 /*
  * get Region
  */
  public function getRegion() {
    return $this->region;
  }
  

  
 /*
  * get Name from a Holiday p.e: "New Year's Day"
  * @param $date: string, datetime-object or timestamp 
  * @param $language string p.E. "de-DE", "en-GB" 
  * @return string name if ok, false Error or Date is not a Holiday,
  *  string "?" no Name for the language in Database  
  */
  public function holidayName($date = "today", $language = null){
    if(is_string($date)) {
      $date = date_create($date)->format("Y-m-d");
    } elseif($date instanceof DateTime) {
      $date = $date->format("Y-m-d");
    } elseif(is_int($date)) {
      $date = date("Y-m-d", $date);
    } else {
      throw new Exception("Error ".__METHOD__.": Parameter date '$date' incorrect"); 
    }
    
    $id = $this->getId($date);
    if(!$id) return false;  //holiday by date not found
    
    if($language === null) $language = $this->language;
    $name = $this->getHolidayNameById($id, $language);

    return $name;
  }

 /*
  * return array( date => holidayname, ..)
  * @param year integer full year p.E. 2018
  * @param $language string p.E. "en_GB"
  */
  public function holidayList($year = null, $language = null){
    if(empty($year)) $year = date('Y');
    if($language === null) $language = $this->language;

    $hList = array();
    foreach($this->config as $id => $row){
      $curDate = $this->getDateFromDBrow($row, $year);
      if($curDate === false) continue;

      $hList[$curDate] = $this->getHolidayNameById($id, $language);
    }
    ksort($hList); 
    return $hList;
  }

 /*
  * return true id if date is a holiday or false
  * @param $date: string, datetime-object or timestamp 
  */
  public function isHoliday($date = 'today'){
    if(is_string($date)) {
      $date = date_create($date)->format("Y-m-d");
    } elseif($date instanceof DateTime) {
      $date = $date->format("Y-m-d");
    } elseif(is_int($date)) {
      $date = date("Y-m-d", $date);
    } else {
      throw new Exception("Error ".__METHOD__.": Parameter date '$date' incorrect"); 
    }
    
    return $this->getId($date) ? true : false;
    
  }
  
 /*
  * get List of Names from DB as 
  * array(idholiday => name, ..) by nameFilter and language
  * return false if not found
  * @param nameFilter: Filter for name , caseinsenitive 
  * @param language string how de or de-ch, default Default Language
  * @param onlyCurrentRegion bool, default false
  */
  public function getNames($nameFilter = "", $language = null, $onlyCurrentRegion = false) {
    if($language === null) $language = $this->language;
    $sql = "SELECT idholiday, name
      FROM names 
      WHERE language LIKE :language COLLATE NOCASE"; 
      $param = array("language" => "%".$language."%");
    if($nameFilter != "") {
      $sql .= " AND name LIKE :nameFilter COLLATE NOCASE";
      $param['nameFilter'] = "%".$nameFilter."%";
    }
    $stmt = $this->pdo->prepare($sql);
    $executeOk = $stmt->execute($param);
    $row = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    if($row AND $onlyCurrentRegion) {
      $row = array_intersect_key($row, $this->config);  
    }
    return $row ? $row : false; 
  }
  
 /*
  * get List of Names from DB as 
  * array(idholiday => name, ..) by nameFilter and language
  * return false if not found
  * @param nameFilter: Filter for name , caseinsenitive 
  * @param yearStart: integer YYYY , default current year
  * @param countYears:  default 1 or end of year (end of year > 
  * @param language string how de or de-ch, default Default Language
  */
  public function holidayNameList($nameFilter = "",$yearStart = null, $countYears=1, $language = null) {
    if($yearStart === null) $yearStart = date("Y");
    //countYears or end of year
    if($countYears > 1000 AND $countYears >= $yearStart){
       $countYears = $countYears - $yearStart + 1; 
    }
    //get id => name array
    $idNames = $this->getNames($nameFilter, $language, true);
    if($idNames === false) return false;

    $list = array();
    for($i=0; $i < $countYears; $i++){
      $year = $yearStart + $i;
      foreach($idNames as $id => $HolidayName) {
        if(array_key_exists($id, $this->config)) {
          $curDate = $this->getDateFromDBrow($this->config[$id], $year);
          if($curDate) $list[$curDate] = $HolidayName;
        } else {  
        //id not in current region
        }
      }
    }  
    ksort($list);
    return $list;
  }
 
  //get config, may use as debugging info
  public function getConfig(){
    return $this->config;
  }
  
  // get id from holiday-Table
  // @param $strDate string format "YYYY-MM-DD"
  protected function getId($date){
    list($year,$month,$day) = explode("-",$date);
    foreach($this->config as $id => $row){
      if($date == $this->getDateFromDBrow($row, $year, $month, $day)) {
        return $id;
      }
    }
    return false;
  }
  
 /*
  * get date as string YYYY-MM-DD
  * $code string : special Code 
  * $year 1600 < year < 2100
  * return false if error
  */
  protected function getMovableDate($code, $year, $month = 1, $day = 1){
    $replacements = array(
      '{{year}}' => $year,
      '{{month}}' => $month,
      '{{day}}' => $day,
      '{{easter}}' => $this->getEasterDate($year),
      '{{easter_o}}' => $this->getEasterDate($year,true),
      '{{passover}}' => $this->getPassoverDate($year),
    );
    
    foreach($replacements as $key => $value){
      $code = str_replace($key, $value, $code); 
    }
    
    //check for datelist {{2018:2/3,..}}
    if(preg_match('~\{\{(\d{4}):(.*)\}\}~',$code,$match)) {
      $startYear = $match[1];
      $values = explode(",",$match[2]);
      $key = $year - $startYear; 
      if(array_key_exists($key,$values)) {
        $code = str_replace($match[0],$values[$key],$code);
      } else {
        return false;
      }
    }
    
    //check extends methods
    if(preg_match('~\{\{([a-z]+)\}\}~',$code,$match)) {
      debug::write($code,$match);
      $methodName = $match[1];
      if(method_exists($this, $methodName)) {
        $replacement = $this->$methodName($year, $month, $day);
        $code = str_replace($match[0],$replacement,$code);
      } else { //error
        throw new Exception("Error ".__CLASS__.": unknown special entry ".$match[0]); 
      }      
    }
    
    $modifiers = explode("|", $code);
    $date = date_create($year."-".$month."-".$day);
    foreach($modifiers as $modify) {
      $date->modify($modify); 
      $errArr = date_get_last_errors();
      if($errArr['error_count']) return false;
    }
    
    return $date->format("Y-m-d");
   }
  
  //get easter-date as string YYYY-MM-DD
  protected function getEasterDate($year,$orthodox = false){
    if($orthodox) {
      $flag = CAL_EASTER_ALWAYS_JULIAN;
      $basisDate = $year."-4-3";
    } else {
      $flag = CAL_EASTER_ALWAYS_GREGORIAN;
      $basisDate = $year."-3-21";
    }
    $date = date_create($basisDate)
      ->modify(easter_days($year, $flag).' Days')
    ;
    return $date
      ->modify((-(int)$date->format('w'))." Days")
      ->format("Y-m-d");
  }
  
   /*
  * calculate the first day of Passover (Gauß)
  * @params: $year integer as YYYY, interval 1900 to 2099
  + @return date as string YYY-MM-DD
  */
  protected function getPassoverDate($year){
    $a = (12*$year+12)%19; 
    $b = $year%4;
    $m = 20.0955877 + 1.5542418 * $a + 0.25 * $b - 0.003177794 * $year; 
    $mi = (int)$m;
    $mn = $m-$mi;
    $c = ($mi + 3 * $year + 5 * $b + 1)%7; 
    if($c==2 OR $c==4 OR $c==6) {
      $mi += 1;
    } elseif($c==1 AND $a > 6 AND $mn >= (1367/2160)) {
      $mi += 2;
    } elseif ($c==0 AND $a > 11 AND $mn > (23269/25920)) {
      $mi += 1;
    }
    return date_create($year."-3-13")
      ->modify($mi." Days")
      ->format("Y-m-d"); 
  }  


 /*
  * return data as string YYYY-MM-DD or false
  */
  protected function getDateFromDBrow($row, $year, $month = 1, $day = 1){
    //accept years
    if(strlen($row->year) >= 4) {
      $dbEntry = $row->year;
      if(ctype_digit($dbEntry)) {
        //only YYYY
        if($dbEntry != $year) return false;
      } elseif(preg_match('~^\d{4}-$~',$dbEntry)) {
        //YYYY-
        if($year < (int)$dbEntry) return false;
      } elseif(preg_match('~^-\d{4}$~',$dbEntry)) {
        //-YYYY
        if($year > (-(int)$dbEntry)) return false;
      } elseif(!in_array($year,$this->listToArray($dbEntry))) {
        return false;
      }
    }
        
    if(strlen($row->except_year) >= 1) {
      if($row->except_year == '*') return false;
      if(in_array($year,$this->listToArray($row->except_year))){
        return false;
      }
    }

    $curmonth = $row->month ? $row->month : $month;
    $curday = $row->day ? $row->day : $day;
    if($row->special) {
      //getMovableDate return false if not match or error
      $curDate = $this->getMovableDate($row->special,$year,$curmonth,$curday);
    } else {
      $curDate = sprintf("%04d-%02d-%02d",$year,$curmonth,$curday);
    }
    return $curDate;
  }
  
  //create config-array
  private function createConfig($filterRegion){
    $sql = "SELECT id, year, except_year, month, day, special, region
      FROM holidays 
      ORDER BY except_year DESC, year DESC";
    
    $stmt = $this->pdo->query($sql);
    if($stmt === false) return false;
    $this->config = array();
    $match = false;
    foreach($stmt as $row){
      $dbRegios = explode(",",$row->region);
      foreach($dbRegios as $region) {
        if($match = (stripos($filterRegion,$region) === 0)) break;
      }
      if($match) $this->config[$row->id] = $row;
    }
    $this->region = $filterRegion;
    return true;
  }

  //string list to array
  private function listToArray($strList){
    $strList = preg_replace_callback(
      '/(\d{4})-(\d{4})/',
      function(array $m){
        return implode(",",range($m[1],$m[2]));
      },
      $strList
    );
    return explode(",",$strList);  
  }

  //get Name by id and $language
  private function getHolidayNameById($id, $language){
    $sql = "SELECT name 
      FROM names 
      WHERE idholiday = $id AND language LIKE :language COLLATE NOCASE
      LIMIT 1";
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute(array("language" => "{$language}%"));
    $row = $stmt->fetch();
    if($row) return $row->name;
    return "?";
  }
  
  
}  
