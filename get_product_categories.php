<?php
/*   __________________________________________________
    |  Obfuscated by YAK Pro - Php Obfuscator  2.0.17  |
    |              on 2026-09-11 14:22:00              |
    |    GitHub: https://github.com/pk-fr/yakpro-po    |
    |__________________________________________________|
*/
 goto t32JX; QPkMp: $qbT06->close(); goto nn0rO; pwbAu: $qbT06 = $pIgNX->prepare("\x53\105\114\105\103\x54\x20\151\x64\54\40\156\141\x6d\x65\x2c\x20\x73\153\x75\40\106\x52\x4f\115\40\160\x72\x6f\x64\x75\143\164\x73\x20\127\x48\105\122\x45\40\143\141\x74\145\147\157\x72\x79\137\x69\x64\75\x3f\x20\101\x4e\104\40\x69\163\x5f\141\x63\164\151\x76\145\x3d\x31\x20\x41\116\104\x20\x28\165\163\x65\162\137\151\x64\x3d\60\40\117\x52\40\x75\163\145\162\137\151\x64\75\77\x29\40\117\x52\104\105\x52\40\102\x59\40\156\x61\155\x65\40\x4c\111\x4d\x49\x54\x20\65\x30\60"); goto HgokV; mRfp3: $qoiqe = intval($_GET["\143\x61\x74\x5f\151\x64"]); goto oN8z3; qwML9: $eD1ul = $qbT06->get_result()->fetch_all(MYSQLI_ASSOC); goto QPkMp; hvS5L: $w4iZZ = intval($_SESSION["\165\163\145\162\137\151\x64"] ?? 0); goto pwbAu; oN8z3: session_start(); goto hvS5L; HgokV: $qbT06->bind_param("\x69\x69", $qoiqe, $w4iZZ); goto uY9cZ; h1AzT: $pIgNX = getDBConnection(); goto mRfp3; t32JX: require_once "\143\x6f\156\146\x69\147\x2e\x70\x68\x70"; goto h1AzT; uY9cZ: $qbT06->execute(); goto qwML9; nn0rO: echo json_encode($eD1ul);
