<?php
/*
File Name: 		starttls_sasl_client.php
Creation Date:  Apr 16, 2009
Creation By:  	Ramesh-us( committing-Sundar Kota)
Module Name:	Collaboration 
Sub Module:		Email- Drivers/DB, include
Main Purpose:   Client is unable to get Akken email client to connect to 3rd party hosted exchange server's SMTP using known good settings and account ID/PW.
TS Task ID:		4255
*/
	if($this->PutLine("EHLO $localhost") && $this->VerifyResultLines("250",$responses)>0 && $this->PutLine("STARTTLS") && $this->VerifyResultLines("220",$responses)>0)
	{
		stream_socket_enable_crypto($this->connection, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
		if($this->PutLine("EHLO $localhost") && $this->VerifyResultLines("250",$responses)>0)
		{
			if($this->PutLine("AUTH LOGIN") && $this->VerifyResultLines("334",$responses)>0)
			{
				if($this->PutLine(base64_encode($this->user)) && $this->VerifyResultLines("334",$responses)>0)
				{
					if($this->PutLine(base64_encode($this->password)) && $this->VerifyResultLines("235",$responses)>0)
					{
						$success=1;
					}
				}
			}
		}
	}
?>