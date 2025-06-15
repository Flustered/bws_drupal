<?php

namespace Drupal\csv_importer\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\node\Entity\Node;

class CsvImportController extends ControllerBase
{

  const CSV_FILENAME = 'BWS_TRAPS-2024-07-29_13-56-05.csv';
  const CURRENT_YEAR = 2024;

  const DATA_TYPE = 'trap'; // The type of data being imported, e.g., 'trap'.

  public function dataimport()
  {


    // open the csv file in the data folder of this module and read it line by line
    $file_path = \Drupal::service('file_system')->realpath('modules/custom/csv_importer/data/' . self::CSV_FILENAME);
    if (!file_exists($file_path)) {
      return new \Symfony\Component\HttpFoundation\JsonResponse([
        'status' => 'error',
        'message' => 'File not found.',
      ]);
    }
    $handle = fopen($file_path, 'r');
    if ($handle === false) {
      return new \Symfony\Component\HttpFoundation\JsonResponse([
        'status' => 'error',
        'message' => 'Failed to open file.',
      ]);
    }
    $header = fgetcsv($handle, 1000, ',');
    if ($header === false) {
      fclose($handle);
      return new \Symfony\Component\HttpFoundation\JsonResponse([
        'status' => 'error',
        'message' => 'Failed to read header.',
      ]);
    }
    $header = array_map('trim', $header);
    $header = array_map('strtolower', $header);
    $header = array_map(function ($value) {
      return preg_replace('/[^a-z0-9_]/', '_', $value);
    }, $header);

    // Read the rest of the file
    $data = [];
    while (($row = fgetcsv($handle, 1000, ',')) !== false) {
      $row = array_map('trim', $row);
      $row = array_map('strtolower', $row);
      $row = array_map(function ($value) {
        return preg_replace('/[^a-z0-9_]/', '_', $value);
      }, $row);
      if (count($row) === count($header)) {
        $data[] = array_combine($header, $row);
      }
    }
    fclose($handle);

    switch (self::DATA_TYPE) {
      case 'trap':
        $this->importTrapData($data, $header);
        break;
      default:
        return new \Symfony\Component\HttpFoundation\JsonResponse([
          'status' => 'error',
          'message' => 'Unsupported data type.',
        ]);
    }



    return new \Symfony\Component\HttpFoundation\JsonResponse([
      'status' => 'success',
      'rows' => count($data),
      'columns' => count($header),
      'data' => $data[1],
      'message' => 'Data import endpoint reached.',
    ]);
  }



  private function importTrapData(array $data, array $header)
  {

    foreach ($data as $key => $row) {

      // check for existing trap node with 'CURRENT_YEAR_trap_id' in field_legacy_trap_id
      $query = \Drupal::entityQuery('node')
        ->condition('type', 'trap')
        ->condition('field_legacy_trap_id', self::CURRENT_YEAR . '_' . $row['trap_id'])
        ->accessCheck(FALSE)
        ->range(0, 1);
      $nids = $query->execute();

      if (empty($row['trap_nickname'])) {
        $trapname = 'Trap ' . self::CURRENT_YEAR . '_' . $row['trap_id'];
      } else {
        $trapname = $row['trap_nickname'];
      }


      if (count($nids) > 0) {

        // If a node exists, load it
        $node = Node::load(current($nids));
      } else {
        // If no node exists, create a new one

        $node = \Drupal\node\Entity\Node::create([
          'type' => 'trap',
          'title' => $trapname,
          'field_legacy_trap_id' => self::CURRENT_YEAR . '_' . $row['trap_id'],
        ]);
      }

      echo $row['trap_postcode'] .  "\n\n";

      // Set the field values

      $node->set('title', $trapname);
      $node->set('field_session', $row['trap_session']);
      $node->set('field_legacy_email_address', $row['user_email']);
      $node->set('field_legacy_username', $row['user_login']);
      $node->set('field_postcode', $row['trap_postcode']);
      $node->set('field_what3words', $row['what3words']);
      $node->set('field_description', $row['trap_description']);

      // make a date from 'friday_september_1_ 2024' to '2024-09-01'
      $date_in = \DateTime::createFromFormat('l_F_j_Y', $row['date_in'] . '_' . self::CURRENT_YEAR);
      if ($date_in) {
        $node->set('field_date_in', $date_in->format('Y-m-d'));
      } else {
        $node->set('field_date_in', '');
      }
      // make a date from 'friday_september_8_2024' to '2024-09-08'
      $date_out = \DateTime::createFromFormat('l_F_j_Y', $row['date_out'] . '_' . self::CURRENT_YEAR);
      if ($date_out) {
        $node->set('field_date_out', $date_out->format('Y-m-d'));
      } else {
        $node->set('field_date_out', '');
      }
      $node->set('field_bottle_colour', $row['bottle_colour']);
      $node->set('field_trap_size', $row['trap_size']);

      $node->set('field_raw_import', json_encode($row));


      // user_roles: "um_super_sampler",
      // user_login: "abeegriffiths",
      // user_email: "mumbee88_gmail_com",
      // trap_id: "35115",
      // trap_status: "trap_done_with_wasps",
      // trap_session: "second",
      // trap_nickname: "cherry_tree",
      // trap_postcode: "tq11_0en",
      // what3words: "",
      // date_out: "friday_september_1",
      // date_in: "friday_september_8",
      // trap_size: "",
      // trap_contents: "",
  
      // wasp_count: "",
      // vespula_germanica: "0",
      // vespula_vulgaris: "4",
      // vespula_rufa: "0",
      // dolichovespula_media: "0",
      // dolichovespula_sylvestris: "0",
      // dolichovespula_saxonica: "0",
      // vespa_crabro: "7",
      // vespa_velutina: "0",
      // trap_description: "",


      $node->save();
    }

  }
}
