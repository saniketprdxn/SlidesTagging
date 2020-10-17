<?php

require_once("functions.php");

//create database connection
$conn = connection();
if(!$conn){
    echo 'Connection Failed';
    exit;
}


//Get id for Lob data
$lobArray = getMasterLob($conn);


//Reading csv file and creating array
$path_to_folder = "";
$file = fopen("Insert_Upload PPTX slides - Sheet1 -OG.csv", "r");
$data = getDataFromCSV($conn, $file, $path_to_folder);
if(empty($data)){
    echo 'No data found.';
    exit;
}


//loop on current array
foreach ($data as $head) {
    foreach ($head as $keys => $subhead) {
        foreach ($subhead as $key => $base) {
            foreach ($base as $key => $value) {

                $conn->beginTransaction();

                //more inputs required
                $slide_id = check_slide($conn, $value);
                if(!$slide_id){
                    $slide_id = insert_slide($conn, $value);
                }

                $slide_id =1;
                //Lob data insert
                $lob_data_status = insert_lob_data($conn, $slide_id, $lobArray, $value['lob']);

                //PPTx data insert
                $pptx_id = insert_pptx_file($conn, $value);

                //Preview Image Data insert
                $preview_image_id = insert_preview_image($conn, $value);

                //Extracted data insert  //more inputs required
                $extracted_text_id = insert_extracted_texts($conn, $value);

                //resource data insertion
                $resource_id = insert_slide_resources($conn, $slide_id, $value['platform'], $pptx_id, $preview_image_id, $extracted_text_id);

                //resource id update
                // if(!update_table($conn, $pptx_id, $resource_id, 'pptx_files', 'resource_id')){
                //     $conn->rollBack();
                //     continue;
                // }

                // if(!update_table($conn, $preview_image_id, $resource_id, 'previews_images', 'resource_id')){
                //     $conn->rollBack();
                //     continue;
                // }

                // if(!update_table($conn, $extracted_text_id, $resource_id, 'extracted_texts', 'resource_id')){
                //     $conn->rollBack();
                //     continue;
                // }

                $conn->commit();
            }
        }
    }
}




