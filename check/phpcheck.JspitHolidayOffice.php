<?php
//error_reporting(-1);
error_reporting(E_ALL ^ (E_WARNING | E_USER_WARNING));
ini_set('display_errors', 1);
header('Content-Type: text/html; charset=UTF-8');

require __DIR__ . '/../class/phpcheck.php';

require __DIR__ . '/../class/JspitHoliday.php';
require __DIR__ . '/../class/icsEventReader.php';

$urlIcs = "https://www.officeholidays.com/ics/ics_country_code.php";

//prepare testdata
$lang = "en";
$publicHoliday = true; //not non public holidays

$countrieRegion = array(
  //true check with regional holidays
  //Euro countries
  "DE" => true, //Germany
  "AT" => true, //Austria 
  "NL" => true, //Netherlands
  "DK" => true, //Denmark
  "FR" => true, //France
  "IT" => true, //Italy
  "ES" => false,//Spain
  "LU" => true, //Luxembourg
  "BE" => true, //Belgium
  "GR" => true, //Greece
  "SK" => true, //Slovakia
  "IE" => true, //Ireland
  "CY" => true, //Cyprus
  "PT" => true, //Portugal
  "EE" => true, //Estonia
  "FI" => true, //Finland
  "LV" => true, //Latvia
  "LT" => true, //Lithuania
  "MT" => true, //Malta
  
  //Other
  "CZ" => true, //Czech Republic
  "PL" => true, //Poland
  "CH" => true, //Switzerland
  "SE" => true, //Sweden

  "GB" => false,//Great Britain
  "US" => false,//United States 
  "JP" => true, //Japan
  "RU" => true, //Russia

);

//all years for check
$years = array(date("Y")-1,date("Y"),date("Y")+1);
//$years = array(2019);

$t = new PHPcheck;  //test-class

foreach($countrieRegion as $countrie => $regionalHoliday) {
  $icsReader = new icsEventReader($urlIcs, $countrie);
  $holiday = new JspitHoliday($countrie."*"); 
  
  foreach($years as $year) {
    $icsReader->reset();  //for getNextEvent
    while($icsEvent = $icsReader->getNextEvent($year, $publicHoliday, $regionalHoliday)) {
      $t->start($icsEvent->date." ".$icsEvent->location." ".$icsEvent->description);
      $result = $holiday->holidayName($icsEvent->date,$lang);
      $t->check($result, $result !== false AND $result != "?");
    }
  }
}

//Output
echo $t->getHtml();
