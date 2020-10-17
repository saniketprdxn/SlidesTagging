<?php

function connection()
{

    $servername = "localhost";
    $username = "root";
    $password = "";
    $dbName = "uhc";

    try {
        $conn = new PDO("mysql:host=$servername;dbname=$dbName", $username, $password);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $conn;
    } catch (PDOException $e) {
        return false;
    }
}


function findParentid($conn, $name, $tracking_id)
{
    $query2 = "SELECT id FROM `base_slides` WHERE `name` LIKE '%" . $name . "%' AND tracking_id = '" . $tracking_id . "' ";

    $run2 = $conn->prepare($query2);
    $run2->execute();
    $result2 = $run2->fetchAll(PDO::FETCH_ASSOC);
    if ($result2 && $result2[0] && $result2[0]['file_path']) {
        return $result2[0]['id'];
    } else {
        return false;
    }
}

function getDataFromCSV($conn, $file, $path_to_folder)
{
    $insertArray = [];
    $head = '';
    $sub_head = '';
    $woZero = '';
    $platform = [];

    if (feof($file)) {
        rewind($file);
    }

    while (!feof($file)) {
        $line = fgetcsv($file);
        if ($line[1] != "" &&
            $line[1] != NULL &&
            $line[1] != "Head" &&
            $line[1] != "Header" &&
            $line[13] != "Insert") {

            $lob = [];
            if ($line[8]) {
                $lob[] = 'SB';
            }
            if ($line[9]) {
                $lob[] = 'KA';
            }
            if ($line[10]) {
                $lob[] = 'NA';
            }
            if ($line[11]) {
                $lob[] = 'PS';
            }

            $lob2 = $line[4];
            if ($lob2 == 'DD') {
                if ((strpos($line[3], 'Detail Deck') == false) || (strpos($line[3], 'Deck') == false)) {
                    $parent_lvl = $line[3] . "/DD";
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

                if (!empty($line[2])) {
                    $pptx_files = $line[1] . "/" . $line[2] . "/" . str_replace('/', ' ', $line[3]) . "/" . str_replace('/', ' ', trim($line[14]));
                    $file_exist = $line[1] . "/" . $line[2] . "/" . $line[3];
                } else {
                    $pptx_files = $line[1] . "/" . str_replace('/', ' ', $line[3]) . "/" . str_replace('/', ' ', trim($line[14]));
                    $file_exist = $line[1] . "/" . $line[3];
                }

                if ($line[16]) {
                    $platform = '1';
                }
                if ($line[17]) {
                    $platform = '2';
                }

                $insertArray[$line[1]][$line[2]][$parent_lvl][] = [
                    'name' => trim($line[12]),
                    'nomenclature' => trim($line[11]),
                    'tracking' => $woZero,
                    'lob' => $lob,
                    'lob2' => $lob2,
                    'pptx' => $pptx_files,
                    'preview' => str_replace('.pptx', '.jpg', trim($pptx_files)),
                    'theme_line' => ($line[15] == 'Yes' || $line[15] == '') ? 1 : 0,
                    'logos' => ($line[16] == 'Yes' || $line[16] == '') ? 1 : 0,
                    'file_exist' => $file_exist,
                    'platform' => $platform,
                ];
            } elseif ($lob2 == 'Single') {
                if (!empty($line[7])) {
                    $dot = strtok($line[7], '.');
                    $wotzero = substr($dot, 1);

                    $ids = explode('.', $line[7]);

                    if (strlen($ids[0]) == 2) {
                        $ids[0] = $wotzero;
                    }
                    $woZero = implode('.', $ids);
                }

                if (!empty($line[2])) {
                    $pptx_files = $line[1] . "/" . $line[2] . "/" . str_replace('/', ' ', trim($line[14]));
                    $file_exist = $line[1] . "/" . $line[2];
                } else {
                    $pptx_files = $line[1] . "/" . str_replace('/', ' ', trim($line[14]));
                    $file_exist = $line[1];
                }

                $insertArray[$line[1]][$line[2]][$line[3]] = [
                    'name' => trim($line[12]),
                    'tracking' => $woZero,
                    'lob' => $lob,
                    'lob2' => $lob2,
                    'pptx' => $pptx_files,
                    'preview' => str_replace('.pptx', '.jpg', $pptx_files),
                    'theme_line' => ($line[15] == 'Yes' || $line[15] == '') ? 1 : 0,
                    'logos' => ($line[16] == 'Yes' || $line[16] == '') ? 1 : 0,
                    'file_exist' => $file_exist,
                    'platform' => $platform,
                ];
            }
        }
    }

    fclose($file);

    return $insertArray;
}


function check_slide($conn, $row)
{
    $query2 = "SELECT id FROM `base_slides` WHERE `name` LIKE '%" . $row['nomenclature'] . "%' AND tracking_id = '" . $row['tracking'] . "' ";

    $run2 = $conn->prepare($query2);
    $run2->execute();
    $result2 = $run2->fetchAll(PDO::FETCH_ASSOC);
    if ($result2 && $result2[0] && $result2[0]['file_path']) {
        return $result2[0]['id'];
    } else {
        return false;
    }
}


function insert_slide($conn, $row)
{
    $sql = "INSERT INTO base_slides(parent_id, required, lft, lvl, rgt, root, name, tracking_id, 
                        description, author, segments, sales_cycles, demo, video, created_at, updated_at, logo_position, enabled,
                        detailed_deck, theme_line, logos, optum_rx) 
                        VALUES(:parent_id, :required, :lft, :lvl, :rgt, :root, :name, :tracking_id, :description, :author,
                               :segments, :sales_cycles, :demo, :video, :created_at, :updated_at, :logo_position, :enabled, 
                               :detailed_deck, :theme_line, :logos, :optum_rx
                        )";
    $result = $conn->prepare($sql);
//    $result->bindParam(':parent_id', $parent_id);
//    $result->bindParam(':required', $required);
//    $result->bindParam(':lft', $lft);
//    $result->bindParam(':lvl', $lvl);
//    $result->bindParam(':rgt', $rgt);
//    $result->bindParam(':root', $root);
//    $result->bindParam(':name', $name);
//    $result->bindParam(':tracking_id', $tracking_id);
//    $result->bindParam(':description', $description);
//    $result->bindParam(':author', $author);
//    $result->bindParam(':segments', $segments);
//    $result->bindParam(':sales_cycles', $sales_cycles);
//    $result->bindParam(':demo', $demo);
//    $result->bindParam(':video', $video);
//    $result->bindParam(':created_at', $created_at);
//    $result->bindParam(':updated_at', $updated_at);
//    $result->bindParam(':logo_position', $logo_position);
//    $result->bindParam(':enabled', $enabled);
//    $result->bindParam(':detailed_deck', $detailed_deck);
//    $result->bindParam(':theme_line', $theme_line);
//    $result->bindParam(':logos', $optum_rx);
    $result->execute();
    $sub_head_id = $conn->lastInsertId();
    return $sub_head_id;
}


function getMasterLob($conn)
{
    $data = array();

    $query = "SELECT id, accronym  FROM `lines_of_business` ";

    $q = $conn->query($query);
    $q->setFetchMode(PDO::FETCH_ASSOC);
    while ($row = $q->fetch()) {
        $data[$row['accronym']] = $row['id'];
    }

    return $data;
}


function insert_lob_data($conn, $slide_id, $lobArray, $lob_data)
{
    $sql = "INSERT INTO lob_data(base_slide_id, line_of_business_id) 
            VALUES (:base_slide_id, :line_of_business_id)";

    foreach ($lob_data as $lob) {
        $lob_id =  $lobArray[$lob];
        $result = $conn->prepare($sql);
        $result->bindParam(':base_slide_id', $slide_id);
        $result->bindParam(':line_of_business_id', $lob_id );
        $result->execute();
    }
    $sub_head_id = $conn->lastInsertId();
    return $sub_head_id;
}


function insert_slide_resources($conn, $slide_id, $platform_id, $pptx_file_id, $preview_image_id, $extracted_text_id)
{
    $logo_position = '';
    $logos = '';

    $sql = "INSERT INTO slide_resources(base_slide_id, platform_id, pptx_file_id, preview_image_id, extracted_text_id, logo_position, logos) 
            VALUES (:base_slide_id, :platform_id, :pptx_file_id, :preview_image_id, :extracted_text_id, :logo_position, :logos)";

    $result = $conn->prepare($sql);
    $result->bindParam(':base_slide_id', $slide_id);
    $result->bindParam(':platform_id', $platform_id);
    $result->bindParam(':pptx_file_id', $pptx_file_id);
    $result->bindParam(':preview_image_id', $preview_image_id);
    $result->bindParam(':extracted_text_id', $extracted_text_id);
    $result->bindParam(':logo_position', $logo_position);
    $result->bindParam(':logos', $logos);
    $result->execute();
    $sub_head_id = $conn->lastInsertId();
    return $sub_head_id;
}


function insert_pptx_file($conn, $row)
{
    $size = '';
    $mime_type = '';
    $extension = '';
    $created_at = '';

    $sql = "INSERT INTO pptx_files( file_path, size, mime_type, extension, created_at) 
            VALUES (:file_path, :size, :mime_type, :extension, :created_at)";

    $result = $conn->prepare($sql);
    $result->bindParam(':file_path', $row['nomenclature']);
    $result->bindParam(':size', $size);
    $result->bindParam(':mime_type', $mime_type);
    $result->bindParam(':extension', $extension);
    $result->bindParam(':created_at', $created_at);
    $result->execute();
    $sub_head_id = $conn->lastInsertId();
    return $sub_head_id;
}


function insert_preview_image($conn, $row)
{
    $size = '';
    $mime_type = '';
    $extension = '';
    $created_at = '';

    $sql = "INSERT INTO previews_images( file_path, size, mime_type, extension, created_at) 
            VALUES (:file_path, :size, :mime_type, :extension, :created_at)";

    $result = $conn->prepare($sql);
    $result->bindParam(':file_path', $row['nomenclature']);
    $result->bindParam(':size', $size);
    $result->bindParam(':mime_type', $mime_type);
    $result->bindParam(':extension', $extension);
    $result->bindParam(':created_at', $created_at);
    $result->execute();
    $sub_head_id = $conn->lastInsertId();
    return $sub_head_id;
}



function insert_extracted_texts($conn, $row)
{
    $level1 = '';
    $level2 = '';
    $level3 = '';
    $level4 = '';
    $slide_number = '';

    $sql = "INSERT INTO extracted_texts(level1, level2, level3, slide_number, level4) 
            VALUES (:level1, :level2, :level3, :slide_number, :level4)";

    $result = $conn->prepare($sql);
    $result->bindParam(':level1', $level1);
    $result->bindParam(':level2', $level2);
    $result->bindParam(':level3', $level3);
    $result->bindParam(':slide_number', $slide_number);
    $result->bindParam(':level4', $level4);
    $result->execute();
    $sub_head_id = $conn->lastInsertId();
    return $sub_head_id;
}

// function update_table($conn, $id, $resource_id, $tablename, $column){

//     $query = "UPDATE $tablename 
//                 SET $column =  $resource_id
//               WHERE id = $id";
//     $result = $conn->prepare($query);
//     $result->execute();
//     $count_row = $result->rowCount();
//     if($count_row > 0){
//         return true;
//     }

//     return false;
// }