<?php PHP

function MakeTime()
{
   $objArgs = func_get_args();
   $nCount = count($objArgs);
   if ($nCount < 7)
   {
       $objDate = getdate();
       if ($nCount < 1)
           $objArgs[] = $objDate["hours"];
       if ($nCount < 2)
           $objArgs[] = $objDate["minutes"];
       if ($nCount < 3)
           $objArgs[] = $objDate["seconds"];
       if ($nCount < 4)
           $objArgs[] = $objDate["mon"];
       if ($nCount < 5)
           $objArgs[] = $objDate["mday"];
       if ($nCount < 6)
           $objArgs[] = $objDate["year"];
       if ($nCount < 7)
           $objArgs[] = -1;
   }
   $nYear = $objArgs[5];
   $nOffset = 0;
   if ($nYear < 1970)
   {
       if ($nYear < 1902)
           return 0;
       else if ($nYear < 1952)
       {
           $nOffset = -2650838400;
           $objArgs[5] += 84;
           // Apparently dates before 1942 were never DST
           if ($nYear < 1942)
               $objArgs[6] = 0;
       }
       else
       {
           $nOffset = -883612800;
           $objArgs[5] += 28;
       }
   }

   return call_user_func_array("mktime", $objArgs) + $nOffset;
}
?>
<?php 
$birthDay = "17/07/1982";
$dateArray = preg_split ( "/\//", $birthDay );
$day = $dateArray[0];
$month = $dateArray[1];
$year = $dateArray[2];
$timestamp  = MakeTime(1, 0, 0, $month  , $day, $year);
print "birthDay is $birthDay<br/>";
print "Timestamp is $timestamp<br/>";
print "Date is " .date("d/m/Y",$timestamp);
?>