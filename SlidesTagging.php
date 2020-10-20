<?php
$servername = "localhost";
$username = "Aniket";
$password = "1234";
$dbName = "Tag_DB";

try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbName", $username, $password);
    // set the PDO error mode to exception
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
        //echo"Connection failed: " . $e->getMessage();
}
$path_to_folder = "LIVE/ppt_files/";
$file = fopen("Tagging UHC to Oxford.xlsx - Batch 1Final.csv", "r");

// getUpdateData($conn, $file, $path_to_folder);
getInsertData($conn, $file, $path_to_folder);

function getInsertData($conn, $file, $path_to_folder)
{
    $insertArray = [];
    $head = '';
    $sub_head = '';
    $woZero = '';
    $platform = [];
    $detailedDeck = 'false';
    if (feof($file)) {
       rewind($file);
    }
    while (!feof($file)) {
        $line = fgetcsv($file);
        if ($line[1] != "" && 
            $line[1] != NULL && 
            $line[1] != "Head" && 
            $line[1] != "Header") {
            pre_r($line);
            if ($line[4] == 'DD') {
                if ((strpos($line[3], 'Detail Deck') == false) || (strpos($line[3], 'Deck') == false)) {
                    $parent_lvl = $line[3]."/DD";
                } else {
                    $parent_lvl = $line[3];
                }
                if (!empty($line[6])) {
                    $dot = strtok($line[6], '.');
                    $wotzero = substr($dot, 1);
                    $ids = explode('.', $line[6]);

                    if (strlen($ids[0]) == 2) {
                        $ids[0] = $wotzero;
                    }
                    $woZero = implode('.', $ids);
                }
                
                if($line[8]) {
                    $platform = '2';
                }

                $insertArray[$line[1]][$line[2]][$parent_lvl][] = array(
                    'name' => trim($line[7]),
                    'tracking' => $woZero,
                    'platform' => $platform,
                );

            } elseif ($line[4] == 'Single') {
                if (!empty($line[6])) {
                    $dot = strtok($line[6], '.');
                    $wotzero = substr($dot, 1);
                    
                    $ids = explode('.', $line[6]);

                    if (strlen($ids[0]) == 2) {
                        $ids[0] = $wotzero;
                    }
                    $woZero = implode('.', $ids);
                }

                if($line[8]) {
                    $platform = '2';
                }

                $insertArray[$line[1]][$line[2]][$line[3]] = array(
                    'name' =>  trim($line[7]),
                    'tracking' => $woZero,
                    'platform' => $platform,
                );
            }
        }
    }

    fclose($file);
    echo "\n\n\n";
    $now = date('Y/m/d H:i:s');
    // pre_r($insertArray);
    // exit();

    foreach ($insertArray as $head_key => $data) {
        $head = $head_key;
        $head_check_query = "SELECT * FROM `base_slides` WHERE `name` LIKE ?";
        $head_check_run = $conn->prepare($head_check_query);
        $head_check_run->execute([$head]);
        $head_data = $head_check_run->fetch(PDO::FETCH_ASSOC);
        $head_id = $head_data['id'];

        if($head_data) {
            $is_head_oxford = platformCheck($conn, $head_data, 2);
            if($is_head_oxford === "false") {
                tagOxford($conn, $head_data, 2);
            }
        }

        foreach ($data as $keys => $subhead) {
            $sub_head = ($keys) ? $keys : "";
            $sub_head_check_query = "SELECT * FROM `base_slides` WHERE `name` LIKE ? AND `parent_id` = ?";
            $sub_head_check_run = $conn->prepare($sub_head_check_query);
            $sub_head_check_run->execute([$sub_head, $head_id]);
            $sub_head_data = $sub_head_check_run->fetch(PDO::FETCH_ASSOC);
            $sub_head_id = $sub_head_data['id'];
            if($sub_head_data) {
                $is_sub_head_oxford = platformCheck($conn, $sub_head_data, 2);
                if($is_sub_head_oxford === "false") {
                    tagOxford($conn, $sub_head_data, 2);
                }
            }

            foreach ($subhead as $key => $value) {

                $DD_status = ((strpos($key, 'Detail Deck')) || (strpos($key, 'Deck')) || (strpos($key, 'DD'))) ? 1 : 0;
                $parent_tracking_id = ((strpos($key, 'Detail Deck')) || (strpos($key, 'Deck')) || (strpos($key, 'DD'))) ? NULL : $value['tracking'];
                if(isset($value[0])) {
                    foreach ($value as $key => $child) {
                        $base_slide_check_query = "SELECT * FROM `base_slides` WHERE `name` LIKE ?";
                        $base_slide_check_run = $conn->prepare($base_slide_check_query);
                        $base_slide_check_run->execute(["%".$child['name']."%"]);
                        $base_slide_data = $base_slide_check_run->fetch(PDO::FETCH_ASSOC);
                        $base_slide_id = $base_slide_data['id'];

                        if($base_slide_data) {
                            $parent_check_query = "SELECT * FROM `base_slides` WHERE `id` = ?";
                            $parent_check_run = $conn->prepare($parent_check_query);
                            $parent_check_run->execute([$base_slide_data['parent_id']]);
                            $parent_data = $parent_check_run->fetch(PDO::FETCH_ASSOC);
                            $parent_id = $parent_data['id'];
                            if($parent_data) {
                                $is_parent_oxford = platformCheck($conn, $parent_data, 2);
                                if($is_parent_oxford === "false") {
                                    $parent_pptx_check_query = "SELECT pptx_file_id FROM `slide_resources` WHERE `base_slide_id` = ?";
                                    $parent_pptx_check_run = $conn->prepare($parent_pptx_check_query);
                                    $parent_pptx_check_run->execute([$parent_id]);
                                    $parent_pptx_data = $parent_pptx_check_run->fetch(PDO::FETCH_ASSOC);
    
                                    if(isset($parent_pptx_data['pptx_file_id']))
                                        fileSystemCheck($conn, $parent_data, 1, $path_to_folder);
                                    else
                                        tagOxford($conn, $parent_data, 2);
                                }
                                $is_base_slide_oxford = platformCheck($conn, $base_slide_data, 2);
                                if($is_base_slide_oxford === "false") {
                                    fileSystemCheck($conn, $base_slide_data, 1, $path_to_folder);
                                }
                            }
                        }
                    }
                } else {

                    $base_slide_check_query = "SELECT * FROM `base_slides` WHERE `name` LIKE ?";
                    $base_slide_check_run = $conn->prepare($base_slide_check_query);
                    $base_slide_check_run->execute([$value['name']]);
                    $base_slide_data = $base_slide_check_run->fetch(PDO::FETCH_ASSOC);
                    $base_slide_id = $base_slide_data['id'];
                    if($base_slide_data) {
                        $is_single_oxford = platformCheck($conn, $base_slide_data, 2);                        
                        if($is_single_oxford === "false") {
                            fileSystemCheck($conn, $base_slide_data, 1, $path_to_folder);
                        }
                    }
                }
            }
        }
    }
}

function platformCheck($conn, $platform_slide_data, $oxford_platform)
{
    $platform_slide_id = $platform_slide_data['id'];
    $platform_check_query = "SELECT * FROM `slide_resources` 
                            WHERE `slide_resources`.`base_slide_id` = :base_slide_id
                            AND `slide_resources`.`platform_id` = ".$oxford_platform;
    $platform_check_run = $conn->prepare($platform_check_query);
    $platform_check_run->bindParam(':base_slide_id', $platform_slide_id);
    $platform_check_run->execute();
    $platform_check_data = $platform_check_run->fetch(PDO::FETCH_ASSOC);

    if(empty($platform_check_data))
        return $is_oxford = "false";
    else 
        return $is_oxford = "true";

}

function tagOxford($conn, $tag_slide_data, $Oxf_platform) 
{

    $already_oxford_check_query = "SELECT * FROM `slide_resources` 
                            WHERE `slide_resources`.`base_slide_id` = :base_slide_id
                            AND `slide_resources`.`platform_id` = ".$Oxf_platform;
    $already_oxford_check_run = $conn->prepare($already_oxford_check_query);
    $already_oxford_check_run->bindParam(':base_slide_id', $tag_slide_data['id']);
    $already_oxford_check_run->execute();
    $already_oxford_check_data = $already_oxford_check_run->fetch(PDO::FETCH_ASSOC);

    if(empty($already_oxford_check_data)) {
        $slide_resources_tag_query = "INSERT INTO slide_resources (base_slide_id,platform_id,logo_position,logos) VALUES (:base_slide_id, :platform, :logo_position_data, :logos)"; 
        $slide_resources_tag_run = $conn->prepare($slide_resources_tag_query);
        $slide_resources_tag_run->bindParam(':base_slide_id', $tag_slide_data['id']);
        $slide_resources_tag_run->bindParam(':platform', $Oxf_platform);
        $slide_resources_tag_run->bindParam(':logo_position_data',  $tag_slide_data['logo_position']);
        $slide_resources_tag_run->bindParam(':logos',  $tag_slide_data['logos']);
        $slide_resources_tag_run->execute();
        // echo "slide resources inserted";
    }
}


function fileSystemCheck($conn, $slide_data, $platform, $path_to_folder)
{
    $slide_id = $slide_data['id'];
    $resource = [];
    $uhc_copy = $oxford_copy = '';
    
    $slide_resources_query = "SELECT * FROM `slide_resources` 
                                WHERE `slide_resources`.`base_slide_id` = :base_slide_id
                                AND `slide_resources`.`platform_id` = ".$platform;
    $slide_resources_run = $conn->prepare($slide_resources_query);
    $slide_resources_run->bindParam(':base_slide_id', $slide_id);
    $slide_resources_run->execute();
    $slide_resources_data = $slide_resources_run->fetch(PDO::FETCH_ASSOC);
    
    if($slide_resources_data) {
        $platform = 2;
        $pptx_check_query = "SELECT * FROM `pptx_files` WHERE `id` = ".$slide_resources_data['pptx_file_id'];
        $pptx_check_run = $conn->prepare($pptx_check_query);
        $pptx_check_run->execute();
        $pptx_data = $pptx_check_run->fetch(PDO::FETCH_ASSOC);

        // Checking in fileSystem And making a oxford copy
        if (file_exists($path_to_folder.$pptx_data['file_path'])) {
            $uhc_copy = $path_to_folder.$pptx_data['file_path'];
            $oxford_copy = str_replace('.pptx', '-o.pptx', $uhc_copy);
            if(file_exists($oxford_copy)) {
                $file_path_to_copy = substr($uhc_copy, 0, strrpos( $uhc_copy, '/'));
                $oxford_slide_path = substr(substr($oxford_copy,strrpos( $oxford_copy, '/')),1);
                $oxford_copy = $file_path_to_copy."/".time()."-".$oxford_slide_path;
                copy($uhc_copy,$oxford_copy);
            } else {
                copy($uhc_copy,$oxford_copy);
            }
        }

        echo $oxford_copy." pptx generated Successfully\n\n";

        // passing that copy to text Extracter Jar
        if (file_exists($oxford_copy)) {

            $Oxford_ppt_path = '';
            $textExtracterResult = '';
            $extracted_text = [];
            $jsonPath = str_replace('.pptx', '_2.json', $oxford_copy);

            // creating path for terminal
            $arr = explode(' ',$oxford_copy);
            foreach($arr as $key => $value) {
                $Oxford_ppt_path.=$value."\\ ";
            }
            $Oxford_ppt_path = substr($Oxford_ppt_path,0,strlen($Oxford_ppt_path)-2);
            $jsonPath_for_cmd = str_replace('.pptx', '_2.json', $Oxford_ppt_path);
            $options = [$Oxford_ppt_path, $jsonPath_for_cmd];

            // text extracter command
            exec("java -jar Pptx/text-extracter.jar " . implode (' ', $options));
            echo " extracted Json generated Successfully\n\n";

            // decoding json file to array
            if (file_exists($jsonPath)) {
                echo $jsonPath;
                $textExtracterResult = json_decode(file_get_contents($jsonPath),true);
                echo " extracted Json decoded Successfully\n\n";
                if(isset($textExtracterResult[0]))
                $extracted_text = [
                    'level1' => $slide_data['name'],
                    'level2' => $textExtracterResult[0]['text'],
                    'level3' => $textExtracterResult[0]['notes'],
                    'level4' => $slide_data['description'],
                    'slide_number' => $textExtracterResult[0]['slide_number'],
                ];
            }
        }

        // passing that copy to convert jar to generate preview
        if (file_exists($oxford_copy)) {

            // logo info json path
            $json_logo_file = substr(substr($oxford_copy,strrpos( $oxford_copy, '/')),1);
            $oxf_json_logo_file = str_replace('.pptx', '.json', $json_logo_file); 
            $oxf_logo_file_path = $path_to_folder."show_logos/".$oxf_json_logo_file;
            // logo info json path for terminal
            $json_logo_file_cmd = substr(substr($Oxford_ppt_path,strrpos( $Oxford_ppt_path, '/')),1);
            $oxf_json_logo_file_cmd = str_replace('.pptx', '.json', $json_logo_file_cmd); 
            $oxf_logo_file_path_cmd = $path_to_folder."show_logos/".$oxf_json_logo_file_cmd;

            // preview image file path
            $oxf_preview_path = substr(substr($oxford_copy,strrpos( $oxford_copy, '_files')),6);
            $oxf_preview_file = str_replace('.pptx', '.jpg', $oxf_preview_path);
            $oxf_preview_file_path = "LIVE/previews".$oxf_preview_file;
            // pptx file path
            $oxf_preview_path_cmd = substr(substr($Oxford_ppt_path,strrpos( $Oxford_ppt_path, '_files')),6);
            $oxf_preview_file_cmd = str_replace('.pptx', '.jpg', $oxf_preview_path_cmd);
            $oxf_preview_file_path_cmd = "LIVE/previews".$oxf_preview_file_cmd;

            // slidemaster file path
            $slide_master_oxford = 'Pptx/slidemaster-o.pptx';
            $options = [$slide_master_oxford,$Oxford_ppt_path, $oxf_preview_file_path_cmd, $oxf_logo_file_path_cmd];
            exec("java -jar Pptx/convert.jar " . implode (' ', $options));
            echo $oxf_preview_file_path." preview generated Successfully\n\n";
        }

        // Logo info data
        if(file_exists($oxf_logo_file_path)) {
            $logo_position = $logos_status = '';
            $logo_info = json_decode(file_get_contents($oxf_logo_file_path), true);
            echo " logo_info Json decoded Successfully\n\n";
            if(!$logo_info['error_status'] === false || !$logo_info['error_status'] == "false") {
                $logo_position = ($logo_info['alignment']) ? $logo_info['alignment'] : "top";
                $logos_status = ($logo_info['status']) ? $logo_info['status'] : '0';
            }
        }

        // PPT data insertion in database
        $now = new DateTime();
        $pptx_size = filesize($oxford_copy);
        $pptx_mime_type = trim(mime_content_type($oxford_copy));
        $pptx_extension = end(explode('.', $oxford_copy));
        $timestring = $now->format('Y-m-d h:i:s');
        $path_to_remove = 'LIVE/ppt_files/';
        $pptx_insert_file_path = str_ireplace($path_to_remove, '', $oxford_copy);

        $already_inserted_pptx_check_query = "SELECT * FROM `pptx_files` WHERE `file_path` = ?";
        $already_inserted_pptx_check_run = $conn->prepare($already_inserted_pptx_check_query);
        $already_inserted_pptx_check_run->execute([$pptx_insert_file_path]);
        $already_inserted_pptx_data = $already_inserted_pptx_check_run->fetch(PDO::FETCH_ASSOC);

        if (empty($already_inserted_pptx_data)) {
            $pptx_insert_query = "INSERT INTO pptx_files (file_path, size, mime_type,extension,created_at,updated_at) VALUES (:pptx, :size, :mime_type, :extension, :created_at, :updated_at)";
            $pptx_insert_run = $conn->prepare($pptx_insert_query);
            $pptx_insert_run->bindParam(':pptx', $pptx_insert_file_path);
            $pptx_insert_run->bindParam(':size', $pptx_size);
            $pptx_insert_run->bindParam(':mime_type', $pptx_mime_type);
            $pptx_insert_run->bindParam(':extension', $pptx_extension);
            $pptx_insert_run->bindParam(':created_at', $timestring);
            $pptx_insert_run->bindParam(':updated_at', $timestring);
            $pptx_insert_run->execute();
            $pptx_last_inserted_id = $conn->lastInsertId();
            echo $pptx_last_inserted_id." pptx inserted Successfully\n\n";
        }

        // preview image data insertion in database
        $path_to_remove = 'LIVE/previews/';
        $preview_insert_file_path = str_ireplace($path_to_remove, '', $oxf_preview_file_path);
        $preview_size = filesize($oxf_preview_file_path);
        $preview_mime_type = trim(mime_content_type($oxf_preview_file_path));
        $preview_extension = end(explode('.', $oxf_preview_file_path));

        $already_inserted_preview_check_query = "SELECT * FROM `previews_images` WHERE `file_path` = ?";
        $already_inserted_preview_check_run = $conn->prepare($already_inserted_preview_check_query);
        $already_inserted_preview_check_run->execute([$preview_insert_file_path]);
        $already_inserted_preview_data = $already_inserted_preview_check_run->fetch(PDO::FETCH_ASSOC);

        if (empty($already_inserted_pptx_data)) {
            $preview_insert_query = "INSERT INTO previews_images (file_path,size,mime_type,extension,created_at,updated_at) VALUES (:preview, :size, :mime_type, :extension, :created_at, :updated_at)";
            $preview_insert_run = $conn->prepare($preview_insert_query);
            $preview_insert_run->bindParam(':preview', $preview_insert_file_path);
            $preview_insert_run->bindParam(':size', $preview_size);
            $preview_insert_run->bindParam(':mime_type', $preview_mime_type);
            $preview_insert_run->bindParam(':extension', $preview_extension);
            $preview_insert_run->bindParam(':created_at', $timestring);
            $preview_insert_run->bindParam(':updated_at', $timestring);
            $preview_insert_run->execute();
            $preview_last_inserted_id = $conn->lastInsertId();
            echo $preview_last_inserted_id." preview inserted Successfully\n\n";                
        }

        // extracted text data insertion in database
        $extracted_insert_query = "INSERT INTO extracted_texts (level1,level2, level3, slide_number, level4) VALUES (?,?,?,?,?)";
        $extracted_insert_run = $conn->prepare($extracted_insert_query);
        $extracted_insert_run->execute([($extracted_text['level1']) ? $extracted_text['level1'] : NULL,($extracted_text['level2']) ? $extracted_text['level2'] : NULL,($extracted_text['level3']) ? $extracted_text['level3'] : NULL,($extracted_text['slide_number']) ? $extracted_text['slide_number'] : NULL, ($extracted_text['level4']) ? $extracted_text['level4'] : NULL]);
        $ext_txt_last_inserted_id = $conn->lastInsertId();
        echo $ext_txt_last_inserted_id." extracted text inserted Successfully\n\n";

        $already_inserted_slide_resource_check_query = "SELECT * FROM `slide_resources`
        WHERE `slide_resources`.`base_slide_id` = :base_slide_id AND `slide_resources`.`platform_id` = ".$platform;
        $already_inserted_slide_resource_check_run = $conn->prepare($already_inserted_slide_resource_check_query);
        $already_inserted_slide_resource_check_run->bindParam(':base_slide_id', $slide_data['id']);
        $already_inserted_slide_resource_check_run->execute();
        $already_inserted_slide_resource_data = $already_inserted_slide_resource_check_run->fetch(PDO::FETCH_ASSOC);

        if (empty($already_inserted_pptx_data)) {
            // slide resources data insertion in database
            $slide_resources_insert_query = "INSERT INTO slide_resources (base_slide_id,platform_id,pptx_file_id,preview_image_id,extracted_text_id,logo_position,logos) VALUES (:base_slide_id, :platform, :pptx_ids, :search_preview_data, :search_extracted_text_data, :logo_position_data, :logos)"; 
            $slide_resources_insert_run = $conn->prepare($slide_resources_insert_query);
            $slide_resources_insert_run->bindParam(':base_slide_id', $slide_data['id']);
            $slide_resources_insert_run->bindParam(':platform', $platform);
            $slide_resources_insert_run->bindParam(':pptx_ids', $pptx_last_inserted_id);
            $slide_resources_insert_run->bindParam(':search_preview_data', $preview_last_inserted_id);
            $slide_resources_insert_run->bindParam(':search_extracted_text_data', $ext_txt_last_inserted_id);
            $slide_resources_insert_run->bindParam(':logo_position_data', $logo_position);
            $slide_resources_insert_run->bindParam(':logos', $logos_status);
            $slide_resources_insert_run->execute();
            $slide_resources_last_insert_id = $conn->lastInsertId();
            echo $slide_resources_last_insert_id." slide resource inserted Successfully\n\n";            
        }

        // create a json output of slide_resources [metadata building]
        if (file_exists($oxford_copy)) {
            $json_metadata_file = substr(substr($oxford_copy,strrpos( $oxford_copy, '/')),1);
            $oxf_json_metadata_file = str_replace('.pptx', '.json', $json_metadata_file);
            $metadata_file_path =  "LIVE/ppt_files/".$oxf_json_metadata_file;
            $slidesDefinition = [];
            $slidesDefinition = [
                'id' => $slide_resources_last_insert_id.'[1]',
                'updatedDate' => $slide_data['updated_at'],
            ];
            $json_data = json_encode($slidesDefinition);
            file_put_contents($metadata_file_path, $json_data);
            echo $metadata_file_path." metadata generated Successfully\n\n";
        }
    }
}

function pre_r($array) 
{
    echo "<pre>";
    if(is_array($array)) {
        print_r($array);
    } else {
        echo $array;
    }
    echo "</pre>";
}
