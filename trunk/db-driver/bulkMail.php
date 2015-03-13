<?php
	// Set include folder
	$include_path=dirname(__FILE__);
	ini_set("include_path",$include_path);

	require("global.inc");
	require("smtp.inc");
	require("html2text.inc");
	require("saveemails.inc");
	require("emailApplicationTrigger.php");

	// Instance SMTP
	$smtp=new smtp_class;
	$smtp->saveCopy=false;

	// Bulk mail statuses
	$stat_bulk="'CampaignIP','PostingIP'";

	$dque="select capp_info.comp_id from company_info LEFT JOIN capp_info ON capp_info.sno=company_info.sno where company_info.status='ER' ".$version_clause;
	$dres=mysql_query($dque,$maindb);
	if(mysql_num_rows($dres)>0)
	{
		// Checking the count of Companyies
		while($drow=mysql_fetch_row($dres))
		{
			// Fetch the Companyies one by one
			$companyuser=strtolower($drow[0]);
			require("maildatabase.inc");
			require("database.inc");

			$ubque="SELECT DISTINCT users.username FROM users LEFT JOIN mail_headers ON users.username=mail_headers.username WHERE users.usertype!='' AND users.status!='DA' AND mail_headers.folder='outbox' AND mail_headers.status in (".$stat_bulk.")";
			$ubres=mysql_query($ubque,$db);
			if(mysql_num_rows($ubres)>0)
			{
				//Checking the Mails count
				while($ubrow=mysql_fetch_row($ubres))
				{
					$username=$ubrow[0];

					$olSync="N";
					$mailidList="";
					$campaignlist="";
					$postinglist="";

					$oque="SELECT plugin_outlook FROM sysuser WHERE username='$username'";
					$ores=mysql_query($oque,$db);
					$orow=mysql_fetch_row($ores);
					$olSync=$orow[0];

                    $queid="select mailid,status from mail_headers where folder='outbox' and status in (".$stat_bulk.") and username='$username'";
                    $resid=mysql_query($queid,$db);
                    while($rsid=mysql_fetch_row($resid))
                    {
                        $mailidList.=$rsid[0].",";

                        if($rsid[1]=='CampaignIP')
                            $campaignlist.=$rsid[0].",";
                        else
                            $postinglist.=$rsid[0].",";
                    }

                    $mailidList=substr($mailidList,0,(strlen($mailidList)-1));

                    // Locking mail to not use for next driver execution
                    if($campaignlist!="")
                    {
                        $campaignlist=substr($campaignlist,0,(strlen($campaignlist)-1));
                        $que="update mail_headers set status='CampaignIP-l' where mailid IN (".$campaignlist.")";
                        mysql_query($que,$db);
                    }

                    if($postinglist!="")
                    {
                        $postinglist=substr($postinglist,0,(strlen($postinglist)-1));
                        $que="update mail_headers set status='PostingIP-l' where mailid IN (".$postinglist.")";
                        mysql_query($que,$db);
                    }

					$que="select a.bccadd,a.fromadd,a.subject,'',a.mailid,a.attach,a.conid,a.status,a.xmltype,a.charset as charset from mail_headers a where a.mailid IN (".$mailidList.")";
					$res=mysql_query($que,$db);
					if(mysql_num_rows($res)>0)
					{
						//Checking the Mails count
						while($row=mysql_fetch_array($res))
						{
							$file_name=array();
							$file_size=array();
							$file_type=array();
							$tempfile=array();
							$arrTotal=array();
							$hfattachments="";
							$flag="";
							$attach="N";

							$mailid=$row[4];

							$bque="select b.body from mail_headers_body b where b.id=$mailid";
							$bres=mysql_query($bque,$maildb);
							$brow=mysql_fetch_array($bres);

							$bcc=$row[0];
							$from=$row[1];
							$subject=$row[2];
							$matter=$brow[0];
							$mail_attach=$row[5];
							$cmntid=$row[6];
							$statusmail=$row[7];
							$xmltype=$row[8];
							$msgs=$mailid;
							$CharSet_mail=AssignEmailCharset($row['charset']);
							$sentsubject=encodedMailsubject($CharSet_mail,$subject,'B');
							$efrom=$from;
							$matter.="<br><center>{{UNSUBSCRIBE}}</center><br>";

							require("setSMTP.php");

							if($statusmail=='CampaignIP-l')
								$cque="select campaign_list.id,campaign_list.rreceipt,campaign_list.sno from campaign_list left join cmngmt_pr on campaign_list.sno=cmngmt_pr.tysno left join mail_headers on cmngmt_pr.sno=mail_headers.conid where mail_headers.mailid='$mailid'";
							else
								$cque="select job_post_det.seqnumber,job_post_det.rreceipt,job_post_det.sno from job_post_det left join cmngmt_pr on job_post_det.sno=cmngmt_pr.tysno left join mail_headers on cmngmt_pr.sno=mail_headers.conid where mail_headers.mailid='$mailid'";

							$cres=mysql_query($cque,$db);
							$crow=mysql_fetch_row($cres);
							$cpid=$crow[0];
							$rreceipt_var = $crow[1];

							$i=0;
							$flag=0;
							$attach_body="";
							$attach_body="";
							$inlineattach_body="";
							$mailheaders=array();
							$attach_folder="";
							$attach_folder=mktime(date("H"),date("i"),date("s"),date("m"),date("d"),date("Y"));
							$attach_folder.=$mailid;//Added in case two mails in outbox are processed with in a sec

							if($mail_attach=="A")
							{
								$aque="select filename,filesize,filetype,filecontent from mail_attachs where mailid='$mailid' and filename!='' and inline!='true'";
								$ares=mysql_query($aque,$maildb);
								while($arow=mysql_fetch_array($ares))
								{
									$file_name[$i]=$arow['filename'];
									$file_type[$i]=$arow['filetype'];
									$tfile=$arow['filename'];
									$isDirEx=$WDOCUMENT_ROOT."/".$attach_folder;
									if(!is_dir($isDirEx))
										mkdir($isDirEx,0777);

									$file=$isDirEx."/".$tfile;
									$fp = fopen($file,"w");
									fwrite($fp, $arow['filecontent']);
									fclose($fp);
									$file_size[$i]=filesize($file);
									$tempfile[$i]=$tfile."|-".stripslashes($arow['filename']);
									$i++;
									$flag++;
								}

								$attachments=implode(",",$file_name);
								$hfattachments=implode("|^",$file_name);
								$hfAttach=$hfattachments;
								$attachType=implode(",",$file_type);
								$asize=implode("|^",$file_size);
								$sesstr=implode("|^",$tempfile);
								$flag=prepareAttachsNEW($file_name,$file_size,$file_type,$file_con,$attach_body);
								$attach = $flag=="0" ? "NA" : "A";
							}

							if($rreceipt_var=='Y')
							{
								if($statusmail=="CampaignIP-l")
									$mailheaders=array("Date: $curtime_header","From: $from","To: ","Subject: $sentsubject","HotList: true","Disposition-Notification-To: $from","MIME-Version: 1.0","Xmlid: $cpid");
								else
									$mailheaders=array("Date: $curtime_header","From: $from","To: ","Subject: $sentsubject","HotReq: true","Disposition-Notification-To: $from","MIME-Version: 1.0","Xmlid: $cpid");
							}
							else
							{
								if($statusmail=="CampaignIP-l")
									$mailheaders=array("Date: $curtime_header","From: $from","To: ","Subject: $sentsubject","HotList: true","MIME-Version: 1.0","Xmlid: $cpid");
								else
									$mailheaders=array("Date: $curtime_header","From: $from","To: ","Subject: $sentsubject","HotReq: true","MIME-Version: 1.0","Xmlid: $cpid");
							}
							$msg_body=prepareBody($matter,$mailheaders,"text/html");

							$secrethash=strtolower(md5(time()));
							$cmque="INSERT INTO campaigns (comp_id,camp_id,secrethash,cdate,status,type) VALUES ('$companyuser','$cpid','$secrethash',NOW(),'A','".$statusmail[0]."')";
							$cmres=mysql_query($cmque,$maindb);
							$cmcid=mysql_insert_id($maindb);


							/*===================================================================================================================================================*/
							/*==================== Get Mail Id s from recipient_info table and create the mail format and sending================================================*/
							/*===================================================================================================================================================*/

							$Rpl_matter="";
							$Rpl_msg_body="";
							$tsucsent = 0;

							$SentArray=array();
							$Mail_status_array['false']=array();
							$Mail_status_array['true']=array();
							$mailAddtionalInfo = array();
							
							$detque="SELECT mailid,IF(addressbook_det='','0',addressbook_det),IF(cont_det='','0',cont_det),IF(cand_det='','0',cand_det),status FROM recipient_info WHERE mailid='$mailid'";
							$detres=mysql_query($detque,$db);
							if(mysql_num_rows($detres)>0)
							{
								$detrow=mysql_fetch_row($detres);
								$ndque = "(SELECT sc.sno,TRIM(IF(sc.email='',IF(sc.email_2='',sc.email_3,sc.email_2),sc.email)) as email,p.name,fname,mname,lname,s.name,sc.email as email_1,sc.email_2,sc.email_3 FROM staffoppr_contact sc LEFT JOIN manage p ON p.sno=sc.prefix LEFT JOIN manage s ON s.sno=sc.suffix WHERE sc.status='ER' AND (FIND_IN_SET('$username',sc.accessto)>0 OR sc.owner='$username' OR sc.accessto='ALL') AND sc.crmcontact='Y' AND (sc.email!='' OR sc.email_2!='' OR sc.email_3!='') AND sc.dontemail != 'Y' AND sc.sno IN (".$detrow[2]."))
								UNION 
								(SELECT sno,TRIM(IF(email='',IF(alternate_email='',other_email,alternate_email),email)) as email,'',fname,mname,lname,'',email as email_1,alternate_email as email_2,other_email as email_3 FROM candidate_list WHERE status='ACTIVE' AND (owner='$username' OR FIND_IN_SET('$username',accessto )>0 OR accessto='ALL') AND (email!='' OR alternate_email!='' OR other_email!='') AND dontemail != 'Y' AND sno IN (".$detrow[3]."))
								UNION 
								(SELECT serial_no,TRIM(IF(email='',IF(email1='',email2,email1),email)) as email,'',fname,mname,lname,'',email as email_1,email1 as email_2,email2 as email_3 FROM contacts WHERE status NOT IN ('INACTIVE','backup') AND userid='$username' AND (email!='' OR email1!='' OR email2!='') AND serial_no in (".$detrow[1]."))
								ORDER BY email";
								$ndres=mysql_query($ndque,$db);
								while($ndrow=mysql_fetch_row($ndres))
								{
									$str_mail_status	= '';

									if(trim($ndrow[1])!="")
									{
										$csque="SELECT COUNT(1) FROM campaigns_unsubscribe WHERE email='".addslashes($ndrow[1])."'";
										$csres=mysql_query($csque,$db);
										$csrow=mysql_fetch_row($csres);
										if($csrow[0]<=0)
										{
											$uslink=genUnsubscribeLink($cmcid,$ndrow[1],$secrethash);
	
											$Rpl_msg_body=$msg_body;
											$Rpl_msg_body=preg_replace("/&lt;Salutation&gt;|<Salutation>|&amp;lt;Salutation&amp;gt;+$/",$ndrow[2],$Rpl_msg_body);
											$Rpl_msg_body=preg_replace("/&lt;Firstname&gt;|<Firstname>|&amp;lt;Firstname&amp;gt;+$/",$ndrow[3],$Rpl_msg_body);
											$Rpl_msg_body=preg_replace("/&lt;Middlename&gt;|<Middlename>|&amp;lt;Middlename&amp;gt;+$/",$ndrow[4],$Rpl_msg_body);
											$Rpl_msg_body=preg_replace("/&lt;Lastname&gt;|<Lastname>|&amp;lt;Lastname&amp;gt;+$/",$ndrow[5],$Rpl_msg_body);
											$Rpl_msg_body=preg_replace("/&lt;Suffix&gt;|<Suffix>|&amp;lt;Suffix&amp;gt;+$/",$ndrow[6],$Rpl_msg_body);
											$Rpl_msg_body=preg_replace("/{{UNSUBSCRIBE}}/","<a target=_blank href='".$uslink."'>One-click unsubscribe from all future emails.</a>",$Rpl_msg_body);
											$Rpl_msg_body=$Rpl_msg_body.$attach_body;
	
											$Rpl_matter=$matter;
											$Rpl_matter=preg_replace("/&lt;Salutation&gt;|<Salutation>|&amp;lt;Salutation&amp;gt;+$/",$ndrow[2],$Rpl_matter);
											$Rpl_matter=preg_replace("/&lt;Firstname&gt;|<Firstname>|&amp;lt;Firstname&amp;gt;+$/",$ndrow[3],$Rpl_matter);
											$Rpl_matter=preg_replace("/&lt;Middlename&gt;|<Middlename>|&amp;lt;Middlename&amp;gt;+$/",$ndrow[4],$Rpl_matter);
											$Rpl_matter=preg_replace("/&lt;Lastname&gt;|<Lastname>|&amp;lt;Lastname&amp;gt;+$/",$ndrow[5],$Rpl_matter);
											$Rpl_matter=preg_replace("/&lt;Suffix&gt;|<Suffix>|&amp;lt;Suffix&amp;gt;+$/",$ndrow[6],$Rpl_matter);
	
											$To_Array=array();
											array_push($To_Array,$ndrow[1]);
											$mailheaders[2]="To: ".$ndrow[1];
											
											$bccArray=explode(",",preg_replace("/ +/", "", $bcc));
											if($campaignlist!="")
											{
												$genDetails = array($ndrow[2],$ndrow[3],$ndrow[4],$ndrow[5],$ndrow[6]);
	
												if(!isset($mailAddtionalInfo[$ndrow[7]]) && $ndrow[7] != "")
													$mailAddtionalInfo[$ndrow[7]] = $genDetails;
												if(!isset($mailAddtionalInfo[$ndrow[8]]) && $ndrow[8] != "")
													$mailAddtionalInfo[$ndrow[8]] = $genDetails;
												if(!isset($mailAddtionalInfo[$ndrow[9]]) && $ndrow[9] != "")
													$mailAddtionalInfo[$ndrow[9]] = $genDetails;
											}
	
											if(!in_array($ndrow[1],$SentArray) && in_array($ndrow[1],$bccArray))
											{
												$suc=$smtp->SendMessage($from,$To_Array,$mailheaders,$Rpl_msg_body);
												if($suc)
												{
													$str_mail_status	= 'sent';
													$tsucsent++;
													array_push($SentArray,$ndrow[1]);
													array_push($Mail_status_array['true'],$ndrow[1]);
												}
												else
												{
													$str_mail_status	= 'failed';
													array_push($Mail_status_array['false'],$ndrow[1]);
												}
	
												if($olSync == "N" && $statusmail == "CampaignIP-l")
												{
													$upd_camp_qry	= "UPDATE campaign_userinfo SET mail_status='".$str_mail_status."' WHERE campaign_sno=".$crow[2]." AND email='".$ndrow[1]."'";
													mysql_query($upd_camp_qry, $db);
												}
											}
										}
										else
										{
											$upd_camp_qry = "UPDATE campaign_userinfo SET mail_status='Unsubscribe' WHERE campaign_sno=".$crow[2]." AND email='".$ndrow[1]."'";
											mysql_query($upd_camp_qry, $db);
										}
									}
								}

								if($bcc!="")
								{
									$sbcc=explode(",",$bcc);
									$lemail=array_unique(array_diff($sbcc,$SentArray));

									if(count($lemail)>0)
									{
										foreach($lemail as $e_key => $e_val)
										{
											$str_mail_status	= '';
											$e_val = trim($e_val);
											$To_Array=array();
											array_push($To_Array,$e_val);
											$mailheaders[2]="To: ".$e_val;
			
											if(!in_array($e_val,$SentArray))
											{
												$csque="SELECT COUNT(1) FROM campaigns_unsubscribe WHERE email='".addslashes($e_val)."'";
												$csres=mysql_query($csque,$db);
												$csrow=mysql_fetch_row($csres);
												if($csrow[0]<=0)
												{
													$uslink=genUnsubscribeLink($cmcid,$e_val,$secrethash);
	
													if(isset($mailAddtionalInfo[$e_val]))
													{
														$Rpl_msg_body=$msg_body;
														$Rpl_msg_body=preg_replace("/&lt;Salutation&gt;|<Salutation>|&amp;lt;Salutation&amp;gt;+$/",$mailAddtionalInfo[$e_val][0],$Rpl_msg_body);
														$Rpl_msg_body=preg_replace("/&lt;Firstname&gt;|<Firstname>|&amp;lt;Firstname&amp;gt;+$/",$mailAddtionalInfo[$e_val][1],$Rpl_msg_body);
														$Rpl_msg_body=preg_replace("/&lt;Middlename&gt;|<Middlename>|&amp;lt;Middlename&amp;gt;+$/",$mailAddtionalInfo[$e_val][2],$Rpl_msg_body);
														$Rpl_msg_body=preg_replace("/&lt;Lastname&gt;|<Lastname>|&amp;lt;Lastname&amp;gt;+$/",$mailAddtionalInfo[$e_val][3],$Rpl_msg_body);
														$Rpl_msg_body=preg_replace("/&lt;Suffix&gt;|<Suffix>|&amp;lt;Suffix&amp;gt;+$/",$mailAddtionalInfo[$e_val][4],$Rpl_msg_body);
														$Rpl_msg_body=$Rpl_msg_body.$attach_body;
														
														
														$Rpl_matter=$matter;
														$Rpl_matter=preg_replace("/&lt;Salutation&gt;|<Salutation>|&amp;lt;Salutation&amp;gt;+$/",$mailAddtionalInfo[$e_val][0],$Rpl_matter);
														$Rpl_matter=preg_replace("/&lt;Firstname&gt;|<Firstname>|&amp;lt;Firstname&amp;gt;+$/",$mailAddtionalInfo[$e_val][1],$Rpl_matter);
														$Rpl_matter=preg_replace("/&lt;Middlename&gt;|<Middlename>|&amp;lt;Middlename&amp;gt;+$/",$mailAddtionalInfo[$e_val][2],$Rpl_matter);
														$Rpl_matter=preg_replace("/&lt;Lastname&gt;|<Lastname>|&amp;lt;Lastname&amp;gt;+$/",$mailAddtionalInfo[$e_val][3],$Rpl_matter);
														$Rpl_matter=preg_replace("/&lt;Suffix&gt;|<Suffix>|&amp;lt;Suffix&amp;gt;+$/",$mailAddtionalInfo[$e_val][4],$Rpl_matter);										
													}
													else
													{
														$Rpl_msg_body=$msg_body;
														$Rpl_msg_body=preg_replace("/&lt;Salutation&gt;|<Salutation>|&amp;lt;Salutation&amp;gt;+$/","",$Rpl_msg_body);
														$Rpl_msg_body=preg_replace("/&lt;Firstname&gt;|<Firstname>|&amp;lt;Firstname&amp;gt;+$/","",$Rpl_msg_body);
														$Rpl_msg_body=preg_replace("/&lt;Middlename&gt;|<Middlename>|&amp;lt;Middlename&amp;gt;+$/","",$Rpl_msg_body);
														$Rpl_msg_body=preg_replace("/&lt;Lastname&gt;|<Lastname>|&amp;lt;Lastname&amp;gt;+$/","",$Rpl_msg_body);
														$Rpl_msg_body=preg_replace("/&lt;Suffix&gt;|<Suffix>|&amp;lt;Suffix&amp;gt;+$/","",$Rpl_msg_body);
														$Rpl_msg_body=$Rpl_msg_body.$attach_body;
														
														
														$Rpl_matter=$matter;
														$Rpl_matter=preg_replace("/&lt;Salutation&gt;|<Salutation>|&amp;lt;Salutation&amp;gt;+$/","",$Rpl_matter);
														$Rpl_matter=preg_replace("/&lt;Firstname&gt;|<Firstname>|&amp;lt;Firstname&amp;gt;+$/","",$Rpl_matter);
														$Rpl_matter=preg_replace("/&lt;Middlename&gt;|<Middlename>|&amp;lt;Middlename&amp;gt;+$/","",$Rpl_matter);
														$Rpl_matter=preg_replace("/&lt;Lastname&gt;|<Lastname>|&amp;lt;Lastname&amp;gt;+$/","",$Rpl_matter);
														$Rpl_matter=preg_replace("/&lt;Suffix&gt;|<Suffix>|&amp;lt;Suffix&amp;gt;+$/","",$Rpl_matter);
													}
	
													$Rpl_msg_body=preg_replace("/{{UNSUBSCRIBE}}/","<a target=_blank href='".$uslink."'>One-click unsubscribe from all future emails.</a>",$Rpl_msg_body);
													$suc=$smtp->SendMessage($from,$To_Array,$mailheaders,$Rpl_msg_body);
	
													if($suc)
													{
														$str_mail_status	= 'sent';
														$tsucsent++;
														array_push($SentArray,$e_val);
														array_push($Mail_status_array['true'],$e_val);
													}
													else
													{
														$str_mail_status	= 'failed';
														array_push($Mail_status_array['false'],$e_val);
													}
	
													if ($olSync == "N" && $statusmail == "CampaignIP-l")
													{
														$upd_camp_qry	= "UPDATE campaign_userinfo SET mail_status='".$str_mail_status."' WHERE campaign_sno=".$crow[2]." AND email='".$e_val."'";
														mysql_query($upd_camp_qry, $db);
													}
												}
												else
												{
													$upd_camp_qry = "UPDATE campaign_userinfo SET mail_status='Unsubscribe' WHERE campaign_sno=".$crow[2]." AND email='".$e_val."'";
													mysql_query($upd_camp_qry, $db);
												}
											}
										}
									}
								}
							}

							if(count($Mail_status_array['true'])>0)
							{
								$TrueMailIds="";
								$TrueMailIds=implode(",",$Mail_status_array['true']);
								if($statusmail=="CampaignIP-l")
								{
									$statusr="Campaign";
									$cmnid=insertCmnt($TrueMailIds,$username,$db,$statusr,$cmntid);
								}
								else
								{
									$statusr="Postings";
									$cmnid=insertPost($TrueMailIds,$username,$db,$statusr,$cmntid,$Rpl_matter);
								}
							}

							if($tsucsent>0)
							{
								$que="update mail_headers set status='deleted' where mailid='$mailid'";
								mysql_query($que,$db);

								$Updatedrows=mysql_affected_rows();
								if($Updatedrows>0)
								{
									mysql_query("DELETE FROM recipient_info WHERE mailid='".$mailid."'",$db);
									$UpEfldString="outbox|^**^|-1|^**^|0|^**^|false^fasle";
									update_efolder_operations($UpEfldString);
								}
							}
							else
							{
								if($statusmail=="CampaignIP-l")
									$fstatus="Campaign";
								else
									$fstatus="Postings";

								$que="update mail_headers set status='$fstatus' where mailid='$mailid'";
								mysql_query($que,$db);
							}

							if(is_dir($WDOCUMENT_ROOT))
								exec("rm -fr $WDOCUMENT_ROOT"."/".$attach_folder);
						}
					}
				}
			}
		}
	}
?>