<?php
	//connect to the database
	function connect () {
		$conn = new mysqli('localhost', ':)', ':)', ':)');
		if($conn->connect_error){
			die("Errore di connessione!");
		}
		return $conn;
	}
	
	//cleaning from sql and html
	function san($conn, $string) {
		return htmlentities(mysql_fix_string($conn, $string));
	}
	
	function mysql_fix_string($conn, $string) {
		if (get_magic_quotes_gpc()) $string = stripslashes ($string);
		return $conn->real_escape_string($string);
	}
	
	//html extraction
	function getHTML($url, $timeout){
       $ch = curl_init($url); // initialize curl with given url
       curl_setopt($ch, CURLOPT_USERAGENT, $_SERVER["HTTP_USER_AGENT"]); // set  useragent
       curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // write the response to a variable
       curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // follow redirects if any
       curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout); // max. seconds to execute
       curl_setopt($ch, CURLOPT_FAILONERROR, 1); // stop when it encounters an error
       return @curl_exec($ch);
	}
	/*https://99webtools.com/blog/extract-website-data-using-php/*/
	
	//domain isolation
	function domain($url){
		preg_match('/(https:\/\/|http:\/\/|ftp:\/\/)(\w+)\.(\w+)(\.|)(\w*)/', $url, $matches);
		return $matches;
	}	
	
	//extract all pages using sitemap 
	function extractSitemap($sitemapUrl){
		$sitemap = file_get_contents($sitemapUrl);
		
		//check if the sitemap contains other sitemaps 
		$pointer = strpos($sitemap, "<sitemapindex");
		if($pointer !== FALSE){
			$sitemap = substr($sitemap, ($pointer + 14));
			$pointer = strpos($sitemap, ">");
			$sitemap = substr($sitemap, ($pointer + 1));
			$pointer = strpos($sitemap, "</sitemapindex>");
			$sitemap = substr($sitemap, 0, $pointer);
			
			//extraction of the position of all sitemaps 
			$sitemaps = array();
			$sitemap = explode('sitemap>', $sitemap);
			
			$counter = 1;
			while($counter < count($sitemap)){
				if(preg_match('/<loc>(.*)<\/loc>/', $sitemap[$counter], $matches)){
					array_push($sitemaps, $matches[1]);
				}
				$counter += 2;
			}
			
			//extraction of the content of all sitemaps
			$pages = array();
			$counter = 0;
			while($counter < count($sitemaps)){
				$content = file_get_contents($sitemaps[$counter]);
				$content = explode('url>', $content);
				
				$anOtherCounter = 1;
				while($anOtherCounter < count($content)){
					if(preg_match('/<loc>(.*)<\/loc>/', $content[$anOtherCounter], $matches)){
						$pageUrl = $matches[1];
						
						//checking the priority tag
						if(preg_match('/<priority>(.*)<\/priority>/', $content[$anOtherCounter], $matches)){
							$pagePriority = $matches[1];
						} else {
							$pagePriority = FALSE;
						}
						$page = array($pageUrl, $pagePriority);
						
						array_push($pages, $page);
					}
					$anOtherCounter += 2;
				}
				$counter++;
			}
		} else {
			$pages = array();
			$sitemap = explode('url>', $sitemap);
			$counter = 1;
			while($counter < count($content)){
				if(preg_match('/<loc>(.*)<\/loc>/', $sitemap[$counter], $matches)){
					$pageUrl = $matches[1];
					
					//checking the priority tag
					if(preg_match('/<priority>(.*)<\/priority>/', $sitemap[$counter], $matches)){
						$pagePriority = $matches[1];
					} else {
						$pagePriority = NULL;
					}
					$page = array($pageUrl, $pagePriority);
					
					array_push($pages, $page);
				}
				$counter += 2;
			}
		}
		if(($pages !== array()) AND ($pages !== NULL)){
			return $pages;
		} else {
			return FALSE;
		}
	}
	
	//check if a url exists
	function validateUrl($url){
		$valid = @get_headers($url);
		if(strpos($valid[0], '200')){
			return TRUE;
		} else {
			return FALSE;
		}
	}
	
	//extracts the pages of a site 
	function crawl($site){
		$page = extractInternalLinks($site, $site, array());
		$internalLinks = $page[0];
		$externalSites = $page[1];
		$imageLinks = $page[2];
		
		$counter = 0;
		while($counter < count($internalLinks)){
			
			$dontExtract = $internalLinks;
			array_merge($dontExtract, $externalSites, $imageLinks);
			
			$page = extractInternalLinks($internalLinks[$counter], $site, $internalLinks);
			array_merge($externalSites, $page[1]);
			array_merge($imageLinks, $page[2]);
			array_merge($internalLinks, $page[0]);
			
			$counter++;
		}
		return array($internalLinks, $externalSites, $imageLinks);
	}
	
	//extracts pages from the same site, external site domains and images from a page
	function extractInternalLinks($pageUrl, $domain, array $dontExtract){
		$content = @file_get_contents($pageUrl);
		$content = explode('"', $content);
		
		//validateUrl() is a function that takes a long time to execute, so it tries to minimize the number of times it is used
		
		$internalLinks = array();
		$externalSites = array();
		$imageLinks = array();
		
		//check the contents in quotes to see if they contain links
		$counter = 1;
		while($counter < count($content)){
			//check for absolute links
			if(preg_match('/^[http|ftp]/', $content[$counter])){
				$externalSite = domain($content[$counter]);
				if((!in_array($content[$counter], $dontExtract)) AND (!in_array($externalSite[0], $dontExtract))){
					if(validateUrl($content[$counter])){
						//division between internal and external links
						if(@preg_match("/^$domain/", $content[$counter])){
							array_push($internalLinks, array($content[$counter], NULL));
							array_push($dontExtract, $content[$counter]);
						} else {
							array_push($externalSites, $externalSite[0]);
							array_push($dontExtract, $externalSite[0]);
						}
					}
				}
			//check for relative links
			} else if(preg_match('/^\/(\S+)$/', $content[$counter])){
				if(!in_array(($domain . $content[$counter]), $dontExtract)){
					if(validateUrl($domain . $content[$counter])){
						array_push($internalLinks, array(($domain . $content[$counter]), FALSE));
						array_push($dontExtract, ($domain . $content[$counter]));
					}
				}
			} else if(preg_match('/^(\S+)$/', $content[$counter])){
				if(!in_array(($pageUrl . '/' . $content[$counter]), $dontExtract)){
					if(validateUrl($pageUrl . '/' . $content[$counter])){
						array_push($internalLinks, array(($pageUrl . '/' . $content[$counter]), FALSE));
						array_push($dontExtract, ($pageUrl . '/' . $content[$counter]));
					}
				}
			}
			$counter += 2;
		}
		
		$counter = 0;
		while($counter < count($internalLinks)){
			//separation between images and pages
			if(preg_match('/(\.svg|\.webp|\.png|\.jpg|\.apng|\.avif|\.gif|\.jpeg|\.jfif|\.pjpeg|\.pjp|\.bmp)$/', $internalLinks[$counter][0])){
				array_push($imageLinks, $internalLinks[$counter][0]);
				array_splice($internalLinks, $counter, 1);
			//delete style files
			} else if(preg_match('/.css$/', $internalLinks[$counter][0])){
				array_splice($internalLinks, $counter, 1);
			} else {
				$counter++;
			}
		}
		
		return array($internalLinks, $externalSites, $imageLinks);
	}
	
	//extraction of the pages allowed by robots.txt  
	function checkRobots($robots){
		//extraction of the part of robots related to siren 
		$pointer = strpos($robots, 'User-agent: sirentoctoc');
		if($pointer !== FALSE){
			$robots = substr($robots, ($pointer + 23));
			$pointer = strpos($robots, 'User-agent:');
			if($pointer !== FALSE){
				$robots = substr($robots, 0, $pointer);
			}
		} else {
			$pointer = strpos($robots, 'User-agent: *');
			if($pointer !== FALSE){
				$robots = substr($robots, ($pointer + 13));
				$pointer = strpos($robots, 'User-agent:');
				if($pointer !== FALSE){
					$robots = substr($robots, 0, $pointer);
				}
			}
		}
		//converting * to (.*) for preg_match
		$robots = preg_replace('/\*/', '(.*)', $robots);
		
		//Collection of allowed and disallowed pages in arrays
		$robots = explode(PHP_EOL, $robots);
		
		$counter = 0;
		$allowed = array();
		$disallowed = array();
		while($counter < count($robots)){
			//Elimination of the commented part 
			$pointer = strpos($robots[$counter], '#');
			if($pointer !== FALSE){
				$robots[$counter] = substr($robots[$counter], 0, $pointer);
			}
			
			if(preg_match('/(Allow:|Disallow:)(\s*)(.*)/', $robots[$counter], $matches)){
				if($matches[1] == 'Allow:'){
					array_push($allowed, $matches[3]);
				} else {
					array_push($disallowed, $matches[3]);
				}
			}
			$counter++;
		}
		return array($allowed, $disallowed);
	}
	
	//this function only extracts the domains of external sites and images from a page 
	function extractExternalSites($pageUrl, $domain, array $dontExtract){
		$content = file_get_contents($pageUrl);
		$content = explode('"', $content);
		
		//validateUrl() is a function that takes a long time to execute, so it tries to minimize the number of times it is used
		
		$externalSites = array();
		$imageLinks = array();
		
		//check the contents in quotes to see if they contain links
		$counter = 1;
		while($counter < count($content)){
			//check for absolute links
			if(preg_match('/^(http|ftp)/', $content[$counter])){
				$externalSite = domain($content[$counter]);
				if((!in_array($content[$counter], $dontExtract)) AND (!in_array($externalSite[0], $dontExtract))){
					if(validateUrl($content[$counter])){
						//division between internal and external links
						if(@preg_match("/^$domain(\.svg|\.webp|\.png|\.jpg|\.apng|\.avif|\.gif|\.jpeg|\.jfif|\.pjpeg|\.pjp|\.bmp)$/", $content[$counter])){
							array_push($imageLinks, $content[$counter]);
							array_push($dontExtract, $content[$counter]);
						} else {
							array_push($externalSites, $externalSite[0]);
							array_push($dontExtract, $externalSite[0]);
						}
					}
				}
			//check for relative links
			} else if(preg_match('/^\/(\S+)(\.svg|\.webp|\.png|\.jpg|\.apng|\.avif|\.gif|\.jpeg|\.jfif|\.pjpeg|\.pjp|\.bmp)$/', $content[$counter])){
				if(!in_array(($domain . $content[$counter]), $dontExtract)){
					if(validateUrl($domain . $content[$counter])){
						array_push($imageLinks, ($domain . $content[$counter]));
						array_push($dontExtract, ($domain . $content[$counter]));
					}
				}
			} else if(preg_match('/^(\S+)(\.svg|\.webp|\.png|\.jpg|\.apng|\.avif|\.gif|\.jpeg|\.jfif|\.pjpeg|\.pjp|\.bmp)$/', $content[$counter])){
				if(!in_array(($pageUrl . '/' . $content[$counter]), $dontExtract)){
					if(validateUrl($pageUrl . '/' . $content[$counter])){
						array_push($imageLinks, ($pageUrl . '/' . $content[$counter]));
						array_push($dontExtract, ($pageUrl . '/' . $content[$counter]));
					}
				}
			}
			$counter += 2;
		}
		
		return array($externalSites, $imageLinks);
	}
	
	//find references to external links and images in the pages declared by a sitemap
	function crawlBySitemap(array $pages, $site){
		$counter = 0;
		$dontExtract = array();
		$externalSites = array();
		$imageLinks = array();
		
		while($counter < count($pages)){
			$page = extractExternalSites($pages[$counter][0], $site, $dontExtract);
			if($externalSites !== array()){
				array_merge($externalSites, $page[0]);
			} else {
				$externalSites = $page[0];
			}
			if($imageLinks !== array()){
				array_merge($imageLinks, $page[1]);
			} else {
				$imageLinks = $page[1];
			}
			if($dontExtract !== array()){
				array_merge($dontExtract, $page[0], $page[1]);
			} else {
				$dontExtract = $page[0];
				array_merge($dontExtract, $page[1]);
			}
			
			$counter++;
		}
		return array($externalSites, $imageLinks);
	}
	
	//comparison of the current data in the table with those collected so far and the robots to determine which changes need to be made
	function updatePages(array $pages, array $pagesInDatabase, array $robots){
		//filtering of pages following the robots
		$pagesToDelete = array();
		
		//only allowed pages can be saved in the database
		if(count($robots[1]) == 0){
			//operation on pages in the database
			$counter = 0;
			while($counter < count($pagesInDatabase)){
				$anOtherCounter = 0;
				$deletePage = TRUE;
				while(($anOtherCounter < count($robots[0])) AND ($deletePage)){
					$robot = $robots[0][$anOtherCounter];
					$robot = preg_replace('/\//', '\/', $robot);
					$robot = '/' . $robot . '/';
					if(preg_match($robot, $pagesInDatabase[$counter][1])){
						$deletePage = FALSE;
					}
					$anOtherCounter++;
				}
				if($deletePage){
					array_push($pagesToDelete, $pagesInDatabase[$counter][0]);
				}
				$counter++;
			}
			
			//operation on the pages collected now
			$counter = 0;
			while($counter < count($pages)){
				$anOtherCounter = 0;
				$deletePage = TRUE;
				while(($anOtherCounter < count($robots[0])) AND ($deletePage)){
					$robot = $robots[0][$anOtherCounter];
					$robot = preg_replace('/\//', '\/', $robot);
					$robot = '/' . $robot . '/';
					if(@preg_match($robot, $pagesInDatabase[$counter][1])){
						$deletePage = FALSE;
					}
					$anOtherCounter++;
				}
				if($deletePage){
					array_splice($pages, $counter, 1);
				} else {
					$counter++;
				}
			}
		//pages that are not disallowed can be saved in the database
		} else {
			//operation on pages in the database
			$counter = 0;
			while($counter < count($pagesInDatabase)){
				$anOtherCounter = 0;
				$deletePage = FALSE;
				while(($anOtherCounter < count($robots[1])) AND (!$deletePage)){
					$robot = $robots[1][$anOtherCounter];
					$robot = preg_replace('/\//', '\/', $robot);
					$robot = '/' . $robot . '/';
					if(preg_match($robot, $pagesInDatabase[$counter][1])){
						$deletePage = TRUE;
					}
					$anOtherCounter++;
				}
				if($deletePage){
					array_push($pagesToDelete, $pagesInDatabase[$counter][0]);
				}
				$counter++;
			}
			
			//operation on the pages collected now
			$counter = 0;
			while($counter < count($pages)){
				$anOtherCounter = 0;
				$deletePage = FALSE;
				while(($anOtherCounter < count($robots[1])) AND (!$deletePage)){
					$robot = $robots[1][$anOtherCounter];
					$robot = preg_replace('/\//', '\/', $robot);
					$robot = '/' . $robot . '/';
					if(@preg_match($robot, $pagesInDatabase[$counter][1])){
						$deletePage = TRUE;
					}
					$anOtherCounter++;
				}
				if($deletePage){
					array_splice($pages, $counter, 1);
				} else {
					$counter++;
				}
			}
		}
		
		$pagesToCreate = array();
		$priorityToUpdate = array();
		
		$counter = 0;
		while($counter < count($pages)){
			$anOtherCounter = 0;
			$doesntExist = TRUE;
			while(($anOtherCounter < count($pagesInDatabase)) AND ($doesntExist)){
				//search for a page that already exists in the database to be updated
				if($pages[$counter][0] == $pagesInDatabase[$anOtherCounter][1]){
					//check if the priority needs to be updated
					if($pages[$counter][1] !== $pagesInDatabase[$anOtherCounter][2]){
						array_push($priorityToUpdate, array($pagesInDatabase[$anOtherCounter][0], $pages[$counter][1]));
					}
					$doesntExist = FALSE;
				}
				$anOtherCounter++;
			}
			//check if this page does not exist in the database yet
			if($doesntExist){
				array_push($pagesToCreate, $pages[$counter]);
			}
			$counter++;
		}
		
		return array($pagesToCreate, $pagesToDelete, $priorityToUpdate);
	}
	
	//comparison of the current data in the table with those collected so far to determine which changes need to be made
	function minimalUpdatePages(array $pages, array $pagesInDatabase){
		$pagesToCreate = array();
		$priorityToUpdate = array();
		
		$counter = 0;
		while($counter < count($pages)){
			$anOtherCounter = 0;
			$doesntExist = TRUE;
			while(($anOtherCounter < count($pagesInDatabase)) AND ($doesntExist)){
				//search for a page that already exists in the database to be updated
				if($pages[$counter][0] == $pagesInDatabase[$anOtherCounter][1]){
					//check if the priority needs to be updated
					if($pages[$counter][1] !== $pagesInDatabase[$anOtherCounter][2]){
						array_push($priorityToUpdate, array($pagesInDatabase[$anOtherCounter][0], $pages[$counter][1]));
					}
					$doesntExist = FALSE;
				}
				$anOtherCounter++;
			}
			//check if this page does not exist in the database yet
			if($doesntExist){
				array_push($pagesToCreate, $pages[$counter]);
			}
			$counter++;
		}
		
		return array($pagesToCreate, $priorityToUpdate);
	}
?>
