<?php 
    /*
        This function expects an input from user that is made like slug: ex. Shtepi per kater vete ne Tirane

        This code is used to search for sentences in database, first it tries to find result with the whole sentence, if there is no result, the code seperates the sentence based on words
        and removes words starting from the end of the sentence till it finds a matching result. If the sentence is trimmed down to one word and there is no result it will
        try to search with each word in the sentence one by one 
        
        ex. Shtepi per kater vete ne Tirane
            Shtepi kater vete Tirane
            Shtepi kater vete
            Shtepi
            Tirane
            kater
            vete
    */

    function slugSearchByKeyword($lang,$searchUrl)
    {
        
        App::setLocale($lang ?? 'en');
        $gjuha = $lang == 'en' ? 'gj2' : 'gj1';

        $keywordsWithRegex = str_replace("+","[[:>:]]|[[:<:]]",$searchUrl);
        $keywords = str_replace("+"," ",$searchUrl);
        $test = explode(" ",$keywords);
        $count = count($test);
        
        if($count == 1)
        {
            $array = $test;
            goto end;
        }
        
        $j = 0;
        $k = $count;
        $array1 = $array2 = array();
        
        for ($i=0; $i < $count; $i++) { 
            if($i == $count-1){
                array_push($array1,"[[:<:]]".$test[$i]."[[:>:]]"."<br>");
                $i = $j++;
            }else{
                array_push($array1,"[[:<:]]".$test[$i]."[[:>:]]|");
            }
        }
​
        $count = count($test);
        $j = 0;
        $k = $count;
​
        a:
        for ($i=0; $i < $count; $i++) { 
            if($i == $count-2){
                array_push($array2,"[[:<:]]".$test[$i]."[[:>:]]"."<br>");
                $i = 0;
                $count--;
                goto a;
            }else if($i < $count-1){
                array_push($array2,"[[:<:]]".$test[$i]."[[:>:]]|");
            }
        }
        $array1 = explode("<br>",implode("",$array1));
        $array2 = explode("<br>",implode("",$array2));
        unset($array1[count($array1)-1],$array1[count($array1)-1]);
        unset($array2[count($array2)-1],$array2[count($array2)-1]);
        $array = array_merge(array_values($array1),array_values($array2));
        
        usort($array, function($a, $b) {
            return strlen($b) <=> strlen($a);
        });

        end:

        $queryAdd = '';

        foreach ($array as $key => $value) {
            $queryAdd .= 'WHEN `adresa` REGEXP "'.$value.'" THEN '.$key.' ';
        }
    
        $queryAdd .= ' ELSE '.count($array).'
        END, adresa ASC';

        $searchProperties = DB::select('SELECT
        ArtID as id,
        ArtTitull_' . $gjuha . ' as title,
        case when( rented = 1 or sold =1 ) then watermark else Artpic end as pic,
        Cmimi AS price,
        ArtPermbajtja_' . $gjuha . ' as body,
        Llojiprones_' . $gjuha . ' as type,
        Qyteti_' . $gjuha . ' as city,
        Forsale as sale,
        Forrent as rent,
        Siperfaqia as area,
        kodi
        from `cms_artikujt` 
        WHERE `adresa` is not NULL AND `adresa`!="" AND ( ifnull(fshih,0)=0 
        AND `adresa` REGEXP "[[:<:]]'.$keywordsWithRegex.'[[:>:]]" 
        OR Keywords_' . $gjuha . ' REGEXP "[[:<:]]'.$keywordsWithRegex.'[[:>:]]" 
        OR Llojiprones_' . $gjuha . ' REGEXP "[[:<:]]'.$keywordsWithRegex.'[[:>:]]" 
        AND `fshih`=0 
        AND deleted_at IS NULL)
        ORDER BY CASE '.$queryAdd
        );

        $forSale = 1;
        $forRent = 1;
        $city = "";
        $values = "";
        $searchFor = $keywords;

        $mapProperties = Cache::remember('index_map_result' . $gjuha . '_' . $forSale . '_' . $forRent . '_' . $city, 15,
        function () use ($gjuha, $forSale, $forRent, $city) {
            return DB::select('SELECT
            ArtID as id,
            ArtTitull_' . $gjuha . ' as title,
            case when( rented = 1 or sold =1 ) then watermark else Artpic end as pic,
            Cmimi as price,
            ArtPermbajtja_' . $gjuha . ' as body,
            Llojiprones_' . $gjuha . ' as type,
            Qyteti_' . $gjuha . ' as city,
            Forsale as sale,
            Forrent as rent,
            Siperfaqia as area,
            koordinata
            from `cms_artikujt`
            WHERE
            ifnull(fshih,0)=0
            and koordinata<>""
            and koordinata IS NOT NULL
            AND (Forsale=? || Forrent =?)
            AND Qyteti_gj2 = ?
            ORDER BY priority DESC,  id DESC limit 100', [$forSale, $forRent, $city]);
        });

        $currentPage = LengthAwarePaginator::resolveCurrentPage();
        $col = new Collection($searchProperties);
        $perPage = 24;
        $currentPageSearchResults = $col->slice(($currentPage - 1) * $perPage, $perPage)->all();
        $pagination = new LengthAwarePaginator($currentPageSearchResults, count($col), $perPage);

        $page = 1;
        if (isset($request->page)) {
            $page = $request->page;
        }

        $pagination->setPath(request()->url());

        return view('contents.search-by-keywords', [
            'pagination' => $pagination,
            'properties' => $currentPageSearchResults, // array_slice($currentPageSearchResults, $page, $perPage),
            'totalProperties' => count($currentPageSearchResults),
            "values" => $values,
            "searchFor" => rtrim(rtrim($searchFor), ','),
            'propertiesMap' => $mapProperties
        ]);
    }
?>
