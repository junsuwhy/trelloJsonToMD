<?php
define('OUTPUT_PATH', 'result/');
if (empty($argv[1])) {
  print("Please enter a filename.\n");
  exit;
}
$executeClassName = $argv[1];
$fileName = $executeClassName.'.json';
include_once($executeClassName.'_var.inc');

$content = file_get_contents($fileName);
global $json;
$json = json_decode($content);

$allCards = array();
foreach ($json->cards as $card) {
  $card->comments = [];
  if (!empty($card->id)) {
    $allCards[$card->id] = $card;
  }
}

/**
 * Solve Tags Name:
 */

$originalLabels = (array)$json->labels;
global $tags;
$tags = array();
foreach ($originalLabels as $label) {
  if (!empty($label)) {
    $tags[$label->id] = $label->name;
  }
}

// 決定要處理的 List
if (empty($needSolveLists)) {
  foreach ($json->lists as $list) {
    $needSolveLists[] = $list->id;
  }
}

// 處理 check list
global $allChecklists;
$allChecklists = [];
foreach ($json->checklists as $checklist) {
  $allChecklists[$checklist->id] = $checklist;
}

// 先處理 Action 到 Cards 上面;
$i = 0;
foreach ($json->actions as $action) {
  unset($card);
  switch ($action->type) {
    case 'createCard':
      $cardId = $action->data->card->id;
      if ($card = &$allCards[$cardId]) {
        $card->create = $action->date;
      }
      break;
    case 'updateCard':
      if (!empty($action->data->listAfter)) {
        # code for move card to list.
      }
      break;
    case 'commentCard':
      $cardId = $action->data->card->id;
      $card = &$allCards[$cardId];
      $commentText = $action->data->text;
      $comment = new stdClass();
      $comment->date = $action->date;
      $comment->text = $commentText;
      if (empty($card->comments)) {
        $card->comments = array();
      }
      $card->comments[] = $comment;
      break;
  }
}


// TODO:

// Attachment


$solvedCards = [];
foreach ($allCards as $card) {

  // 決定是否要處理或是跳過：
  if (in_array($card->idList, $needSolveLists) && !$card->closed && (!empty($card->desc) || !empty($card->comments))) {
    generateMarkdownByCard($card);
  }
}

function generateMarkdownByCard($card) {
  global $tags;
  $mdFile = fopen(OUTPUT_PATH.$card->name.'.md', 'wa+');
  if (!$mdFile) return;
  $text = '---
tags: ';
if (!empty($card->idLabels)) {
    $cardTags = [];
    foreach ($card->idLabels as $labelKey) {
      $cardTags[] = $tags[$labelKey];
    }
    $text .= '
- '.implode('
- ', $cardTags);
  }
  if (isset($card->create)) {
    $createdDateText =  date('Y-m-d H:i:s +0800', strtotime($card->create));
    $text .= '
date: '.$createdDateText;
  }
  if ($card->due && $card->dueComplete) {
    $finishDateText = date('Y-m-d H:i:s +0800', strtotime($card->due));
    $text .= '
finish_date: '.$finishDateText;
  }
  $text .= '
---

'. $card->desc;

  // Card Description

  // Card checklists
  global $allChecklists;
  foreach ($card->idChecklists as $checklistId) {
    if ($checklist = $allChecklists[$checklistId]) {
      $text .= generateCheckList($checklist);
    }
  }

  // Card Comments
  foreach ($card->comments as $comment) {
    $date = date('Y-m-d H:i:s', strtotime($comment->date));
    $text .= "

# {$date}

{$comment->text}

";
  // [x] Trello Link


  }
  $text .= "

[Trello卡片原連結]({$card->shortUrl})";
  fwrite($mdFile, $text);
  fclose($mdFile);

}

function generateCheckList($checklist) {
  $text = '';
  $text .= "

## {$checklist->name}
";
  $options = $checklist->checkItems;
  usort($options, function($a, $b) {
    return ($b->pos < $a->pos);
  });
  foreach ($options as $opt) {
    $result = ($opt->state == 'complete') ? 'x' : ' ';
    $text .= "
  - [{$result}] {$opt->name}";
  }
  return $text;
}

