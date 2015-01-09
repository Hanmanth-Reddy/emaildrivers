<?php
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