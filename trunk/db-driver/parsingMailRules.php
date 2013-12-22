<?php
	function splitKeyWords($words)
	{
		$words=strtolower ($words);
		$words=explode("\"",$words);

		$varc=count($words);
		$temp=array();
		$x=0;
		for($i=0;$i<$varc;$i++)
		{
			if($i%2==0 || $i==0)
			{
				//To seperate string with space which is not enclosed with in double quotes
				$arrtemp=explode(" ",$words[$i]);
				for($j=0;$j<count($arrtemp);$j++)
				{
					$temp[$x]=$arrtemp[$j];
					$x++;
				}
			}
			else
			{
				$temp[$x]=$words[$i];
				$x++;
			}
		}

		for($j=0;$j<count($temp);$j++)
		{
			if($temp[$j]!="")
				$temp1[$j]=$temp[$j];
		}

		$s=0;
		$strtemp1=implode("|",$temp1);
		$temp1=explode("|",$strtemp1);
		for($j=0;$j<count($temp1);$j++)
		{
			$temp2[$s]=$temp1[$j];
			$s++;
			if($j>=0 && $j!=count($temp1)-1)
			{
				//To append "or",If user didn't specify "and" or "or" in between words.
				if($temp1[$j+1]!="and" && $temp1[$j+1]!="or" && $temp1[$j]!="and" && $temp1[$j]!="or" )
				{
					$temp2[$s]="or";
					$s++;
				}
			}
		}
		return $temp2;
	}

    Function searchKeyWords($cmsg,$strCond)
    {
        $isCon=false;
        $isLog="";

        $bFrom1 = 0;
        $cType = substr($strCond, 0, strpos($strCond, "-"));
        $resval = substr($strCond, strpos($strCond, "-")+1);

        $arrspl=array("\\","(",")","+","/",".","$","[","]","{","}","?");

        for($varspl=0;$varspl<=count($arrspl);$varspl++)
            $resval=str_replace($arrspl[$varspl],"\\\\".$arrspl[$varspl],$resval);

        if($resval != "")
            $resval=splitKeyWords($resval);

        if($cType == "C")
        {
            $first="/((^)|([[:space:]]+)|(>))";
            $last="(([[:space:]]+)|(<)|($))/";
        }
        else if($cType == "NC")
        {
            $first="/((^)|([[:space:]]+)|(>))";
            $last="(([[:space:]]+)|(<)|($))/";
        }
        else if($cType == "BW")
        {
            $first="/((^)|(>))";
            $last="/";
        }
        else
        {
            $cmsg = trim(strtolower(strip_tags($cmsg)));
            $first="/";
            $last="((<)|($))/";
        }
        $contents = strtolower($cmsg);

        for($i=0;$i<count($resval);$i++)
        {
            if($i%2==0 || $i==0)
            {
                $resval[$i]=$first.$resval[$i].$last;
                $resval[$i]=str_replace("*","\S*",$resval[$i]);

                if(preg_match ( $resval[$i], $contents))
                {
                    if($isLog=="or")
                        $isCon=true;
                    else if($isCon && $isLog=="and")
                        $isCon=true;
                    else
                        $isCon=false;

                    if($isLog=="")
                        $isCon=true;
                }
                else
                {
                    $isCon=false;
                }
            }
            else
            {
                if($resval[$i]=="and")
                {
                   $isLog="and";
                }
                else if($resval[$i]=="or")
                {
                    $isLog="or";
                    if($isCon)
                    {
                        break;
                    }
                }
            }
        }
        if($cType == "NC")
            return !($isCon);
        else
            return $isCon;
    }
?>