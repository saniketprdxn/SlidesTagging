<?php

require_once("functions.php");

//create database connection
$conn = connection();
if(!$conn){
    echo 'Connection Failed';
    exit;
}

$path_to_folder = "LIVE/ppt_files/";
$file = fopen("Tagging UHC to Oxford.xlsx - Batch run 23_10_20.csv", "r");
$platform_to_tag = 2;
$default_platform = 1;
$keyArray = array();

// getUpdateData($conn, $file, $path_to_folder);
$insertArray = getInsertData($conn, $file, $path_to_folder);
$sortedplatform = DatabaseCheck($insertArray,$default_platform,$platform_to_tag,$conn,$path_to_folder);

function sortAssociativeArray($givenArray) {
    global $keyArray;
   foreach ($givenArray as $key => $value) {
    if(!is_array($value)){
      $keyArray[$key] = $value; 
    } else {
    sortAssociativeArray($value);
    }
   }
   return $keyArray;
}
// pre_r($sortedplatform);

function DatabaseCheck($insertArray,$default_platform,$platform_to_tag,$conn,$path_to_folder)
{
    foreach ($insertArray as $head_key => $data) {
        $head = $head_key;
        $head_data = getBaseSlideFromName($head,$conn);
        $head_id = $head_data['id'];
        $platforms = [];
        if($head_data) {
            $is_head_already_tagged = platformCheck($conn, $head_data, $platform_to_tag);
            if($is_head_already_tagged === "false" || $is_head_already_tagged === false) {
                $sortedData = sortAssociativeArray($data);
                $platforms = array_slice($sortedData,2);
                // return $platforms;
                foreach ($platforms as $key => $platform_to_tag) {
                // tagBaseSlide($conn, $head_data, $platform_to_tag);
                }
            }
        }

        foreach ($data as $keys => $subhead) {
            $sub_head = ($keys) ? $keys : "";
            $sub_head_data = getBaseSlideFromNameAndParentId($sub_head,$head_id,$conn); 
            $sub_head_id = $sub_head_data['id'];
            if($sub_head_data) {
                $is_sub_head_already_tagged = platformCheck($conn, $sub_head_data, $platform_to_tag);
                if($is_sub_head_already_tagged === "false" || $is_sub_head_already_tagged === false) {
                    foreach ($platforms as $key => $platform_to_tag) {
                    // tagBaseSlide($conn, $sub_head_data, $platform_to_tag);
                    }
                }
            }

            foreach ($subhead as $key => $value) {

                $DD_status = ((strpos($key, 'Detail Deck')) || (strpos($key, 'Deck')) || (strpos($key, 'DD'))) ? 1 : 0;
                $parent_tracking_id = ((strpos($key, 'Detail Deck')) || (strpos($key, 'Deck')) || (strpos($key, 'DD'))) ? NULL : $value['tracking'];
                if(isset($value[0])) {
                    foreach ($value as $key => $child) {
                        $base_slide_name = "%".$child['name']."%";
                        $base_slide_data = getBaseSlideFromName($base_slide_name,$conn);
                        $base_slide_id = $base_slide_data['id'];

                        if($base_slide_data) {
                            $parent_data = getBaseSlideFromId($base_slide_data['parent_id'],$conn);
                            $parent_id = $parent_data['id'];
                            if($parent_data) {
                                $is_parent_already_tagged = platformCheck($conn, $parent_data, $platform_to_tag);
                                if($is_parent_already_tagged === "false" || $is_parent_already_tagged === false) {
                                    $parent_pptx_data = getPptxId($parent_id,$conn);
    
                                    if(isset($parent_pptx_data['pptx_file_id'])) {
                                        foreach ($platforms as $key => $platform_to_tag) {
                                        // defaultCheck($conn, $parent_data, $default_platform, $platform_to_tag, $path_to_folder);                                        
                                        }
                                    } else {
                                        foreach ($platforms as $key => $platform_to_tag) {
                                        // tagBaseSlide($conn, $parent_data, $platform_to_tag);                                        
                                        }
                                    }
                                }
                                $is_base_slide_already_tagged = platformCheck($conn, $base_slide_data, $platform_to_tag);
                                if($is_base_slide_already_tagged === "false" || $is_base_slide_already_tagged === false) {
                                    foreach ($platforms as $key => $platform_to_tag) {
                                    // defaultCheck($conn, $base_slide_data, $default_platform, $platform_to_tag, $path_to_folder);                                    
                                    }
                                }
                            }
                        }
                    }
                } else {
                    $base_slide_data = getBaseSlideFromName($value['name'],$conn);
                    $base_slide_id = $base_slide_data['id'];
                    if($base_slide_data) {
                        $is_single_already_tagged = platformCheck($conn, $base_slide_data, $platform_to_tag);                        
                        if($is_single_already_tagged === "false" || $is_single_already_tagged === false) {
                            foreach ($platforms as $key => $platform_to_tag) {
                            // defaultCheck($conn, $base_slide_data, $default_platform, $platform_to_tag, $path_to_folder);                            
                            }
                        }
                    }
                }
            }
        }
    }
}

function defaultCheck($conn, $slide_data, $default_platform, $platform_to_tag, $path_to_folder)
{
    $slide_id = $slide_data['id'];
    $slide_resources_query = "SELECT * FROM `slide_resources` 
                                WHERE `slide_resources`.`base_slide_id` = :base_slide_id
                                AND `slide_resources`.`platform_id` = ".$default_platform;
    $slide_resources_run = $conn->prepare($slide_resources_query);
    $slide_resources_run->bindParam(':base_slide_id', $slide_id);
    $slide_resources_run->execute();
    $slide_resources_data = $slide_resources_run->fetch(PDO::FETCH_ASSOC);
    
    if($slide_resources_data) {
        tagBaseSlideByFileSystem($conn,$slide_resources_data,$slide_data,$platform_to_tag,$path_to_folder);
        echo "tagBaseSlideByFileSystem ".$slide_data['id']."\n\n";
    }
}

function fileSystemCheck($conn,$slide_resources_data,$path_to_folder)
{
    $resource = [];
    $default_copy = $platform_copy = '';
    $pptx_check_query = "SELECT * FROM `pptx_files` WHERE `id` = ".$slide_resources_data['pptx_file_id'];
    $pptx_check_run = $conn->prepare($pptx_check_query);
    $pptx_check_run->execute();
    $pptx_data = $pptx_check_run->fetch(PDO::FETCH_ASSOC);

    // Checking in fileSystem And making a platform copy
    if (file_exists($path_to_folder.$pptx_data['file_path'])) {
        $default_copy = $path_to_folder.$pptx_data['file_path'];
        $platform_copy = str_replace('.pptx', '-o.pptx', $default_copy);
        if(file_exists($platform_copy)) {
            $file_path_to_copy = substr($default_copy, 0, strrpos( $default_copy, '/'));
            $platform_slide_path = substr(substr($platform_copy,strrpos( $platform_copy, '/')),1);
            $platform_copy = $file_path_to_copy."/".time()."-".$platform_slide_path;
           $platform_copy = platformCopy($default_copy,$platform_copy);
           return $platform_copy;
        } else {
            $platform_copy = platformCopy($default_copy,$platform_copy);
            return $platform_copy;
        }
    }    
}

function platformCopy($default_copy,$platform_copy)
{
    copy($default_copy,$platform_copy);
    echo $platform_copy." pptx generated Successfully\n\n";
    return $platform_copy;
}

function extractText($platform_copy,$platform_to_tag,$slide_data)
{
    // passing that copy to text Extracter Jar
    if (file_exists($platform_copy)) {

        $platform_ppt_path_cmd = '';
        $textExtracterResult = '';
        $extracted_text = [];
        $jsonPath = str_replace('.pptx', '_'.$platform_to_tag.'.json', $platform_copy);

        $platform_ppt_path_cmd = '"'.$platform_copy.'"';
        $jsonPath_for_cmd = '"'.str_replace('.pptx', '_'.$platform_to_tag.'.json', $platform_copy).'"';
        $jsonPath = str_replace('.pptx', '_'.$platform_to_tag.'.json', $platform_copy);
        $options = [$platform_ppt_path_cmd, $jsonPath_for_cmd];

        // text extracter command
        exec("java -jar Pptx/text-extracter.jar " . implode (' ', $options));

        // decoding json file to array
        if (file_exists($jsonPath)) {
            $textExtracterResult = json_decode(file_get_contents($jsonPath),true);
            if(isset($textExtracterResult[0]))
            $extracted_text = [
                'level1' => $slide_data['name'],
                'level2' => $textExtracterResult[0]['text'],
                'level3' => $textExtracterResult[0]['notes'],
                'level4' => $slide_data['description'],
                'slide_number' => $textExtracterResult[0]['slide_number'],
            ];
            return $extracted_text;
        } else {
            return $extracted_text = [];
        }
    }
}

function convertJar($platform_copy,$platform_to_tag, $path_to_folder)
{
    // passing that copy to convert jar to generate preview
    if (file_exists($platform_copy)) {
        // logo info json path
        $json_logo_file = substr(substr($platform_copy,strrpos( $platform_copy, '/')),1);
        $platform_json_logo_file = str_replace('.pptx', '.json', $json_logo_file); 
        $platform_logo_file_path = $path_to_folder."show_logos/".$platform_json_logo_file;

        // logo info json path for terminal
        $json_logo_file_cmd = '"'.$platform_logo_file_path.'"';

        // preview image file path
        $platform_preview_path = substr(substr($platform_copy,strrpos( $platform_copy, '_files')),6);
        $platform_preview_file = str_replace('.pptx', '.jpg', $platform_preview_path);
        $platform_preview_file_path = "LIVE/previews".$platform_preview_file;
        // pptx file path
        $platform_ppt_path_cmd = '"'.$platform_copy.'"';
        $platform_preview_file_path_cmd = '"'.$platform_preview_file_path.'"';

        // slidemaster file path
        // $slide_master_query = "SELECT slide_master FROM `platforms` WHERE `platform_id` = ".$platform_to_tag;
        // $slide_master_run = $conn->prepare($slide_master_query);
        // $slide_master_run->execute();
        // $slide_master = $pptx_check_run->fetch(PDO::FETCH_ASSOC);
        // $slide_master = '"'.$slide_master['slide_master'].'"';
        $slide_master = '"Pptx/slidemaster-o.pptx"';
        $options = [$slide_master,$platform_ppt_path_cmd, $platform_preview_file_path_cmd, $json_logo_file_cmd];

        // convert pptx to previews
        exec("java -jar Pptx/convert.jar " . implode (' ', $options));
        return $platform_preview_file_path;
    }  else {
        return NULL;
    }
}

function insertPptx($conn,$platform_copy)
{
    // PPT data insertion in database
    $now = new DateTime();
    $pptx_size = filesize($platform_copy);
    $pptx_mime_type = trim(mime_content_type($platform_copy));
    $pptx_extension = end(explode('.', $platform_copy));
    $timestring = $now->format('Y-m-d h:i:s');
    $path_to_remove = 'LIVE/ppt_files/';
    $pptx_insert_file_path = str_ireplace($path_to_remove, '', $platform_copy);

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
        return $pptx_last_inserted_id;
    } else {
        return NULL;
    }
}

function insertPreview($conn, $platform_copy, $preview_file_path)
{
    // preview image data insertion in database
    $path_to_remove = 'LIVE/previews/';
    $preview_size = filesize($preview_file_path);
    $preview_mime_type = trim(mime_content_type($preview_file_path));
    $preview_extension = end(explode('.', $preview_file_path));
    $preview_insert_file_path = str_ireplace($path_to_remove, '', $preview_file_path);
    
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
        return $preview_last_inserted_id;
    } else {
        return NULL;
    }
}

function insertExtractedText($conn,$extracted_text)
{
    // extracted text data insertion in database
    $extracted_insert_query = "INSERT INTO extracted_texts (level1,level2, level3, slide_number, level4) VALUES (?,?,?,?,?)";
    $extracted_insert_run = $conn->prepare($extracted_insert_query);
    $extracted_insert_run->execute([($extracted_text['level1']) ? $extracted_text['level1'] : NULL,($extracted_text['level2']) ? $extracted_text['level2'] : NULL,($extracted_text['level3']) ? $extracted_text['level3'] : NULL,($extracted_text['slide_number']) ? $extracted_text['slide_number'] : NULL, ($extracted_text['level4']) ? $extracted_text['level4'] : NULL]);
    $ext_txt_last_inserted_id = $conn->lastInsertId();
    return $ext_txt_last_inserted_id;
}

function insertSlideResources($conn,$platform_copy,$platform_to_tag,$insert_data,$path_to_folder)
{
    // logo info json path
    $json_logo_file = substr(substr($platform_copy,strrpos( $platform_copy, '/')),1);
    $platform_json_logo_file = str_replace('.pptx', '.json', $json_logo_file); 
    $platform_logo_file_path = $path_to_folder."show_logos/".$platform_json_logo_file;

    // Logo info data
    if(file_exists($platform_logo_file_path)) {
        $logo_position = $logos_status = '';
        $logo_info = json_decode(file_get_contents($platform_logo_file_path), true);
        echo " logo_info Json decoded Successfully\n\n";
        if(!$logo_info['error_status'] === false || !$logo_info['error_status'] == "false") {
            $logo_position = ($logo_info['alignment']) ? $logo_info['alignment'] : "top";
            $logos_status = ($logo_info['status']) ? $logo_info['status'] : '0';
        }
    }

    // slide resources data insertion in database
    $already_inserted_slide_resource_check_query = "SELECT * FROM `slide_resources`
    WHERE `slide_resources`.`base_slide_id` = :base_slide_id AND `slide_resources`.`platform_id` = ". $platform_to_tag;
    $already_inserted_slide_resource_check_run = $conn->prepare($already_inserted_slide_resource_check_query);
    $already_inserted_slide_resource_check_run->bindParam(':base_slide_id', $insert_data['base_slide']);
    $already_inserted_slide_resource_check_run->execute();
    $already_inserted_slide_resource_data = $already_inserted_slide_resource_check_run->fetch(PDO::FETCH_ASSOC);

    if (empty($already_inserted_pptx_data)) {
        $slide_resources_insert_query = "INSERT INTO slide_resources (base_slide_id,platform_id,pptx_file_id,preview_image_id,extracted_text_id,logo_position,logos) VALUES (:base_slide_id, :platform, :pptx_file, :preview_image, :extracted_text, :logo_position, :logos)"; 
        $slide_resources_insert_run = $conn->prepare($slide_resources_insert_query);
        $slide_resources_insert_run->bindParam(':base_slide_id', $insert_data['base_slide']);
        $slide_resources_insert_run->bindParam(':platform',  $platform_to_tag);
        $slide_resources_insert_run->bindParam(':pptx_file', $insert_data['pptx_file']);
        $slide_resources_insert_run->bindParam(':preview_image', $insert_data['preview_image']);
        $slide_resources_insert_run->bindParam(':extracted_text', $insert_data['extracted_text']);
        $slide_resources_insert_run->bindParam(':logo_position', $logo_position);
        $slide_resources_insert_run->bindParam(':logos', $logos_status);
        $slide_resources_insert_run->execute();
        $slide_resources_last_insert_id = $conn->lastInsertId();
        return $slide_resources_last_insert_id;        
    } else {
        return NULL;
    }
}


function generateMetaData($platform_copy,$slide_resources_last_insert_id, $updated_at)
{
    // create a json output of slide_resources [metadata building]
    if (file_exists($platform_copy)) {
        $json_metadata_file = substr(substr($platform_copy,strrpos( $platform_copy, '/')),1);
        $json_metadata_file = str_replace('.pptx', '.json', $json_metadata_file);
        $metadata_file_path =  "LIVE/ppt_files/".$json_metadata_file;
        $slidesDefinition = [];
        $slidesDefinition = [
            'id' => $slide_resources_last_insert_id.'[1]',
            'updatedDate' => $updated_at,
        ];
        $json_data = json_encode($slidesDefinition);
        file_put_contents($metadata_file_path, $json_data);
        return $metadata_file_path;
    } else {
        return NULL;
    }
}

function tagBaseSlideByFileSystem($conn,$slide_resources_data, $slide_data, $platform_to_tag,$path_to_folder)
{

    $platform_copy = fileSystemCheck($conn,$slide_resources_data,$path_to_folder);

    $extracted_text = extractText($platform_copy,$platform_to_tag,$slide_data);
    echo " extracted Json decoded Successfully\n\n";
    
    $preview_file_path = convertJar($platform_copy,$platform_to_tag, $path_to_folder);
    echo $preview_file_path." preview generated Successfully\n\n";

    $pptx_last_inserted_id = insertPptx($conn, $platform_copy);
    echo $pptx_last_inserted_id." pptx inserted Successfully\n\n";

    $preview_last_inserted_id = insertPreview($conn, $platform_copy, $preview_file_path);
    echo $preview_last_inserted_id." preview inserted Successfully\n\n";

    $ext_txt_last_inserted_id = insertExtractedText($conn, $extracted_text);
    echo $ext_txt_last_inserted_id." extracted text inserted Successfully\n\n";

    $insert_data = [
        'base_slide' => $slide_data['id'],
        'pptx_file' => $pptx_last_inserted_id,
        'preview_image' => $preview_last_inserted_id,
        'extracted_text' => $ext_txt_last_inserted_id
    ];

    $updated_at = $slide_data['updated_at'];

    $slide_resources_last_insert_id = insertSlideResources($conn, $platform_copy,$platform_to_tag,$insert_data, $path_to_folder);
    echo $slide_resources_last_insert_id." slide resource inserted Successfully\n\n";

    $metadata_file_path = generateMetaData($platform_copy,$slide_resources_last_insert_id, $updated_at);
    echo $metadata_file_path." metadata generated Successfully\n\n";

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

function fileSystemCheck_old($conn, $slide_data, $platform, $path_to_folder)
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

            $Oxford_ppt_path_cmd = '';
            $textExtracterResult = '';
            $extracted_text = [];
            $jsonPath = str_replace('.pptx', '_2.json', $oxford_copy);

            $Oxford_ppt_path_cmd = '"'.$oxford_copy.'"';
            $jsonPath_for_cmd = '"'.str_replace('.pptx', '_2.json', $oxford_copy).'"';
            $jsonPath = str_replace('.pptx', '_2.json', $oxford_copy);
            $options = [$Oxford_ppt_path_cmd, $jsonPath_for_cmd];

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
            $json_logo_file_cmd = '"'.$oxf_logo_file_path.'"';
            // preview image file path
            $oxf_preview_path = substr(substr($oxford_copy,strrpos( $oxford_copy, '_files')),6);
            $oxf_preview_file = str_replace('.pptx', '.jpg', $oxf_preview_path);
            $oxf_preview_file_path = "LIVE/previews".$oxf_preview_file;
            // pptx file path
            $oxf_preview_file_path_cmd = '"'.$oxf_preview_file_path.'"';

            // slidemaster file path
            $slide_master_already_tagged = '"Pptx/slidemaster-o.pptx"';
            $options = [$slide_master_oxford,$Oxford_ppt_path_cmd, $oxf_preview_file_path_cmd, $json_logo_file_cmd];
            exec("java -jar Pptx/convert.jar " . implode (' ', $options));
            echo $oxf_preview_file_path." preview generated Successfully\n\n";
        }
        pre_r($oxf_logo_file_path);
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
