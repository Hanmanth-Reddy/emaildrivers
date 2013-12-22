<?php
    function html2text($htmlText)
    {
    	//remove PHP if it exists
    	while( substr_count( $htmlText, '<'.'?' ) && substr_count( $htmlText, '?'.'>' ) && strpos( $htmlText, '?'.'>', strpos( $htmlText, '<'.'?' ) ) > strpos( $htmlText, '<'.'?' ) )
    	{
    		$htmlText = substr( $htmlText, 0, strpos( $htmlText, '<'.'?' ) ) . substr( $htmlText, strpos( $htmlText, '?'.'>', strpos( $htmlText, '<'.'?' ) ) + 2 );
    	}

    	//remove comments
    	while( substr_count( $htmlText, '<!--' ) && substr_count( $htmlText, '-->' ) && strpos( $htmlText, '-->', strpos( $htmlText, '<!--' ) ) > strpos( $htmlText, '<!--' ) )
    	{
    		$htmlText = substr( $htmlText, 0, strpos( $htmlText, '<!--' ) ) . substr( $htmlText, strpos( $htmlText, '-->', strpos( $htmlText, '<!--' ) ) + 3 );
    	}

    	//now make sure all HTML tags are correctly written (> not in between quotes)
    	for( $x = 0, $plainText = '', $is_open_tb = false, $is_open_sq = false, $is_open_dq = false; strlen( $chr = $htmlText{$x} ); $x++ )
    	{
    		//take each letter in turn and check if that character is permitted there
    		switch( $chr )
    		{
    			case '<':
    				if( !$is_open_tb && strtolower( substr( $htmlText, $x + 1, 5 ) ) == 'style' )
    				{
    					$htmlText = substr( $htmlText, 0, $x ) . substr( $htmlText, strpos( strtolower( $htmlText ), '</style>', $x ) + 7 ); $chr = '';
    				}
    				elseif( !$is_open_tb && strtolower( substr( $htmlText, $x + 1, 6 ) ) == 'script' )
    				{
    					$htmlText = substr( $htmlText, 0, $x ) . substr( $htmlText, strpos( strtolower( $htmlText ), '</script>', $x ) + 8 ); $chr = '';
    				}
    				elseif( !$is_open_tb )
    				{
    					$is_open_tb = true;
    				}
    				else
    				{
    					$chr = '&lt;';
    				}
    				break;
    			case '>':
    				if( !$is_open_tb || $is_open_dq || $is_open_sq )
    				{
    					$chr = '&gt;';
    				}
    				else
    				{
    					$is_open_tb = false;
    				}
    				break;
    			case '"':
    				if( $is_open_tb && !$is_open_dq && !$is_open_sq )
    				{
    					$is_open_dq = true;
    				}
    				elseif( $is_open_tb && $is_open_dq && !$is_open_sq )
    				{
    					$is_open_dq = false;
    				}
    				else
    				{
    					$chr = '&quot;';
    				}
    				break;
    			case "'":
    				if( $is_open_tb && !$is_open_dq && !$is_open_sq )
    				{
    					$is_open_sq = true;
    				}
    				elseif( $is_open_tb && !$is_open_dq && $is_open_sq )
    				{
    					$is_open_sq = false;
    				}
    		}
    		$plainText .= $chr;
    	}

    	//now that the page is valid (I hope) for strip_tags, strip all unwanted tags
    	$plainText = strip_tags( $plainText, '<title><hr><h1><h2><h3><h4><h5><h6><div><p><pre><sup><ul><ol><br><dl><dt><table><caption><tr><li><dd><th><td><a><area><img><form><input><textarea><button><select><option>');

    	//strip extra whitespace except between <pre> and <textarea> tags
    	$htmlText = preg_split( "/<\/?pre[^>]*>/i", $plainText );

    	for( $x = 0; is_string( $htmlText[$x] ); $x++ )
    	{
    		if( $x % 2 )
    		{
    			$htmlText[$x] = '<pre>'.$htmlText[$x].'</pre>';
    		}
    		else
    		{
    			$plainText = preg_split( "/<\/?textarea[^>]*>/i", $htmlText[$x] );
    			for( $z = 0; is_string( $plainText[$z] ); $z++ )
    			{
    				if( $z % 2 )
    				{
    					$plainText[$z] = '<textarea>'.$plainText[$z].'</textarea>';
    				}
    				else
    				{
    					$plainText[$z] = preg_replace( "/\s+/", ' ', $plainText[$z] );
    				}
    			}
    			$htmlText[$x] = implode('',$plainText);
    		}
    	}
    	$plainText = implode('',$htmlText);

    	//remove all options from select inputs
    	$plainText = preg_replace( "/<option[^>]*>[^<]*/i", '', $plainText );

    	//replace all tags with their text equivalents
    	$plainText = preg_replace( "/<(\/title|hr)[^>]*>/i", "\n--------------------------------------------------------------------------------\n", $plainText );
    	$plainText = preg_replace( "/<(h|div|p)[^>]*>/i", "\n\n", $plainText );
    	$plainText = preg_replace( "/<sup[^>]*>/i", '^', $plainText );
    	$plainText = preg_replace( "/<(ul|ol|br|dl|dt|table|caption|\/textarea|tr[^>]*>\s*<(td|th))[^>]*>/i", "\n", $plainText );
    	$plainText = preg_replace( "/<li[^>]*>/i", "\n· ", $plainText );
    	$plainText = preg_replace( "/<dd[^>]*>/i", "\n\t", $plainText );
    	$plainText = preg_replace( "/<(th|td)[^>]*>/i", "\t", $plainText );
    	$plainText = preg_replace( "/<a[^>]* href=(\"((?!\"|#|javascript:)[^\"#]*)(\"|#)|'((?!'|#|javascript:)[^'#]*)('|#)|((?!'|\"|>|#|javascript:)[^#\"'> ]*))[^>]*>/i", "[LINK: $2$4$6] ", $plainText );
    	$plainText = preg_replace( "/<img[^>]* alt=(\"([^\"]+)\"|'([^']+)'|([^\"'> ]+))[^>]*>/i", "[IMAGE: $2$3$4] ", $plainText );
    	$plainText = preg_replace( "/<form[^>]* action=(\"([^\"]+)\"|'([^']+)'|([^\"'> ]+))[^>]*>/i", "\n[FORM: $2$3$4] ", $plainText );
    	$plainText = preg_replace( "/<(input|textarea|button|select)[^>]*>/i", "[INPUT] ", $plainText );

    	//strip all remaining tags (mostly closing tags)
    	$plainText = strip_tags( $plainText );

    	//convert HTML entities
    	$plainText = strtr( $plainText, array_flip( get_html_translation_table( HTML_ENTITIES ) ) );
    	preg_replace( "/&#(\d+);/me", "chr('$1')", $plainText );

    	//wordwrap
    	$plainText = wordwrap( $plainText );

    	//make sure there are no more than 3 linebreaks in a row and trim whitespace
    	return preg_replace( "/^\n*|\n*$/", '', preg_replace( "/[ \t]+(\n|$)/", "$1", preg_replace( "/\n(\s*\n){2}/", "\n\n\n", preg_replace( "/\r\n?|\f/", "\n", str_replace( chr(160), ' ', $plainText ) ) ) ) );
    }
?>
