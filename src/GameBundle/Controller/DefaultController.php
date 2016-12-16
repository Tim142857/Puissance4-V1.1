<?php

namespace GameBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use GameBundle\Entity\Grid;
use GameBundle\Entity\Ligne;
use GameBundle\Entity\Colonne;
use GameBundle\Entity\Slot;
use GameBundle\Entity\Player;
use Symfony\Component\HttpFoundation\Request;
use \Symfony\Component\HttpFoundation\JsonResponse;

class DefaultController extends Controller
{
    public function indexAction()
    {
        $em = $this->getDoctrine()->getManager();
        $repoGrid = $em->getRepository('GameBundle:Grid');

        $grid = $repoGrid->getHydratedGrid(41);
        $grids = $this->genereGrids($grid, 1);
        dump($this->getCountFullSlots($grid));
        foreach ($grids as $grid) {
            dump($this->getCountFullSlots($grid));
        }

        return $this->render('GameBundle::index.html.twig');
    }

    public function gameAction($id)
    {
        $em = $this->getDoctrine()->getManager();
        $repoGrids = $em->getRepository('GameBundle:Grid');
        $repoPlayers = $em->getRepository('GameBundle:Player');

        //------------------------CONFIG-------------------------------------
        $IAjoue = false;
        $debug = false;
        $height = $this->getParameter('height-grid');
        $width = $this->getParameter('width-grid');
        //-------------------------------------------------------------------------


        //---------------------------Mise en place de la grille---------------------------
        if ($id != null) {
            //récupération de la grille en base
            $grid = $repoGrids->find($id);
        } else {
            //création de la nouvelle grille
            $grid = new Grid();

            //------------------------Mise en place des joueurs------------------------
            $player1 = new Player();
            $player1->setName('Toto');
            $player1->setColor('red');
            $player1->setGrid($grid);
            if ($IAjoue) {
                $player2 = $repoPlayers->find(1);
            } else {
                $player2 = new Player();
                $player2->setName('Titi');
                $player2->setColor('orange');
                $player2->setGrid($grid);
            }
            $player2->setGrid($grid);

            //Premier joueur à jouer
            $grid->setNextPlayer($player1);


            //----------------------Création des lignes-----------------------------------

            //tableau de tous les slots
            $arraySlots = array();

            for ($i = 0; $i < $height; $i++) {
                $ligne = new Ligne();
                $ligne->setGrid($grid);
                for ($j = 0; $j < $width; $j++) {
                    $slot = new Slot();
                    $slot->setLigne($ligne);
                    //j'enregistre tous mes slots dans mon tableau de slots
                    array_push($arraySlots, $slot);
                }
            }

            //----------------------Création des colonnes-----------------------------------
            for ($i = 0; $i < $width; $i++) {
                $colonne = new Colonne();
                $colonne->setGrid($grid);
            }


            //--------------------------setColonne a chaque slot-----------------------------------
            $arrayColonnes = $grid->getColonnes();
            for ($i = 0; $i < count($arraySlots); $i++) {
                $arraySlots[$i]->setColonne($arrayColonnes[$i % $width]);
            }

        }
        $em->persist($grid);
        $em->flush();

//        dump($repoGrids->find(1)->getColonnes());
        return $this->render('GameBundle::game.html.twig', array('grid' => $grid, 'debug' => $debug));
    }

    public function coupAction(Request $request)
    {

        $em = $this->getDoctrine()->getManager();
        $repoColonne = $em->getRepository('GameBundle:Colonne');
        $repoPlayer = $em->getRepository('GameBundle:Player');
        $repoGrid = $em->getRepository('GameBundle:Grid');
        $idColonne = filter_input(INPUT_POST, 'colonne');
        $idPlayer = filter_input(INPUT_POST, 'player');

        if ($idColonne == 'null' && $idPlayer == '1') {
            $grid = $repoGrid->find(filter_input(INPUT_POST, 'idgrid'));
            //traitement par l'IA
//            dump('IA!');
            $idColonne = $this->retourneRandomIdColonne($grid);

        }

        //Ajout du jeton dans la colonne
        $colonne = $repoColonne->find($idColonne);

        foreach ($colonne->getSlots() as $slot) {
            if ($slot->getPlayer() == null) {
                $slotToEdit = $slot;
            }
        }

        //Si colonne déja remplie
        if (!isset($slotToEdit)) {
            return new JsonResponse(array("error" => "colonne déja remplie!"));
        }


        $player = $repoPlayer->find($idPlayer);


        $slotToEdit->setPlayer($player);
        $em->persist($slotToEdit);
        $em->flush();


        //Verification si victoire

        $grid = $repoGrid->find($player->getGrid());
//        $grid = $repoGrid->getHydratedGrid($player->getGrid());
        dump('1');
        dump($grid);
        $grid->getColonnes();
        dump('2');
        dump($grid);
        $finJeu = $this->finJeu($grid);

        $player2 = '';
        foreach ($grid->getPlayers() as $player3) {
            if ($player3 != $player) {
                $player2 = $player3;
                $grid->setNextPlayer($player2);
                $em->persist($grid);
                $em->flush();
            }

        }

//        $slots = $this->finJeu($player->getGrid());

        $array['idslot'] = $slotToEdit->getId();
        $array['color'] = $player->getColor();
        $array['nextPlayerName'] = $player2->getName();
        $array['nextPlayerId'] = $player2->getId();
        $array['finJeu'] = $finJeu['finJeu'];
        if (isset($finJeu['idGagnant'])) {
            $array['idGagnant'] = $finJeu['idGagnant'];
            $array['nomGagnant'] = $finJeu['nomGagnant'];
        }


        return new JsonResponse($array);
    }

    function finJeu($grid)
    {
        $finJeu['finJeu'] = true;

        $em = $this->getDoctrine()->getManager();
//        $repoColonne = $em->getRepository('GameBundle:Colonne');
        $repoGrid = $em->getRepository('GameBundle:Grid');
        $colonnes = $repoGrid->getHydratedGrid($grid->getId())->getColonnes();
//        $colonnes = $repoColonne->findByGrid($grid);
//        $repoLigne = $em->getRepository('GameBundle:Ligne');

        $slots = array();
        foreach ($colonnes as $colonne) {
            foreach ($colonne->getSlots() as $slot) {
                array_push($slots, $slot);
            }
        }

        //Verification des lignes s'il y a un gagnant
        $results = $this->getSlotsAlignedByLines($grid, 4);

        foreach ($results as $id => $count) {
            if ($count > 0) {
                $finJeu['idGagnant'] = $id;
            }
        }
        //Verification des colonnes s'il y a un gagnant
//        if (!isset($finJeu['idGagnant'])) {
        $results = $this->getSlotsAlignedByColonnes($grid, 4);
//        dump($results);
        foreach ($results as $id => $count) {
            if ($count > 0) {
                $finJeu['idGagnant'] = $id;
            }
        }
//        }


        //Verification des diagonales s'il y a un gagnant

        $results = $this->getSlotsAlignedByDiagonales($grid, 4);
//        dump($results);
//        if (!isset($finJeu['idGagnant'])) {
        foreach ($results as $id => $count) {
            if ($count > 0) {
                $finJeu['idGagnant'] = $id;
            }
        }
//    }


//        foreach ($slots as $slot) {
////            if ($slot->getId() % 7 <= 4) {
//            $player1 = $slot->getPlayer();
//            $slot2 = $repoSlot->find(($slot->getId() - 6));
//            $slot3 = $repoSlot->find(($slot->getId() - 12));
//            $slot4 = $repoSlot->find(($slot->getId() - 18));
//            $player2 = $slot2 == null ? null : $slot2->getPlayer();
//            $player3 = $slot3 == null ? null : $slot3->getPlayer();
//            $player4 = $slot4 == null ? null : $slot4->getPlayer();
//            if ($player1 != null && $player2 != null && $player3 != null && $player4 != null) {
//                if ($player1 == $player2 && $player2 == $player3 && $player3 == $player4) {
//                    $finJeu['idGagnant'] = $player1->getId();
//                    dump('1er cas: /');
//                }
//            }
////            }
//        }

//        foreach ($slots as $slot) {
////            if ($slot->getId() % 7 >= 4) {
//            $player1 = $slot->getPlayer();
//            $slot2 = $repoSlot->find(($slot->getId() + 8));
//            $slot3 = $repoSlot->find(($slot->getId() + 16));
//            $slot4 = $repoSlot->find(($slot->getId() + 24));
//            $player2 = $slot2 == null ? null : $slot2->getPlayer();
//            $player3 = $slot3 == null ? null : $slot3->getPlayer();
//            $player4 = $slot4 == null ? null : $slot4->getPlayer();
//            if ($player1 != null && $player2 != null && $player3 != null && $player4 != null) {
//                if ($player1 == $player2 && $player2 == $player3 && $player3 == $player4) {
//                    $finJeu['idGagnant'] = $player1->getId();
//                    dump('2e cas: \\');
//                }
//            }
////            }
//        }
//
//
        //S'il y a un gagnant, récupération de son nom
        if (isset($finJeu['idGagnant'])) {
            $finJeu['nomGagnant'] = $em->getRepository('GameBundle:Player')->find($finJeu['idGagnant'])->getName();
        }


        //S'il n'y a pas de gagnant, vérification de l'égalité
        if (!isset($finJeu['idGagnant'])) {
            foreach ($slots as $slot) {
                if ($slot->getPlayer() == null) {
                    $finJeu['finJeu'] = false;
                }
            }
        }


        return $finJeu;

    }

    function getSlotsAlignedByLines($grid, $long)
    {
        $em = $this->getDoctrine()->getManager();
        $repoLigne = $em->getRepository('GameBundle:Ligne');
        $lignes = $repoLigne->findByGrid($grid);
        $results = array();

        //Verification des lignes s'il y a un gagnant
        foreach ($lignes as $ligne) {
            for ($i = 0; $i < (count($ligne->getSlots()) - $long); $i++) {
                $arraySlots = $ligne->getSlots();
                $players = array();
                for ($j = 0; $j < $long; $j++) {
                    array_push($players, $arraySlots[$j + $i]->getPlayer());
                }
                $array = array_filter($players, function ($value) use ($players) {
                    if ($value == null) {
                        return false;
                    }
                    return ($value == $players[0]);
                });
                if (count($array) == $long) {
//                    dump('win ligne ' . $ligne->getId());
                    if (isset($results[$players[0]->getId()])) {
                        $results[$players[0]->getId()]++;
                    } else {
                        $results[$players[0]->getId()] = 1;
                    }
                }
            }
        }
        return $results;
    }

    function getSlotsAlignedByColonnes($grid, $long)
    {
        $em = $this->getDoctrine()->getManager();
        $repoColonne = $em->getRepository('GameBundle:Colonne');
        $colonnes = $repoColonne->findByGrid($grid);
        $repoGrid = $em->getRepository('GameBundle:Grid');
        $grid = $repoGrid->getHydratedGrid($grid->getId());


        $results = array();

        //Verification des colonnes s'il y a un gagnant
        foreach ($grid->getColonnes() as $colonne) {
            for ($i = 0; $i <= (count($colonne->getSlots()) - $long); $i++) {
                $testLog = '';
                $arraySlots = $colonne->getSlots();
                $players = array();
                for ($j = 0; $j < $long; $j++) {
                    array_push($players, $arraySlots[$j + $i]->getPlayer());
                    $testLog .= $arraySlots[$j + $i]->getId() . '/';
                }
                $array = array_filter($players, function ($value) use ($players) {
                    if ($value == null) {
                        return false;
                    }
                    return ($value == $players[0]);
                });
                if (count($array) == $long) {
                    if (isset($results[$players[0]->getId()])) {
                        $results[$players[0]->getId()]++;
                    } else {
                        $results[$players[0]->getId()] = 1;
                    }
                }
                dump($testLog);
            }
        }

        return $results;
    }

    function getSlotsAlignedByDiagonales($grid, $long)
    {
        $em = $this->getDoctrine()->getManager();
        $repoGrid = $em->getRepository('GameBundle:Grid');
        $repoSlot = $em->getRepository('GameBundle:Slot');
        $grid = $repoGrid->getHydratedGrid($grid->getId());
        $results = array();

        //Je récuère l'ensemble de mes slots dans un tableau
        $slots = array();
        foreach ($grid->getLignes() as $ligne) {
            foreach ($ligne->getSlots() as $slot) {
                array_push($slots, $slot);
            }
        }

        $width = $this->getParameter('width-grid');
        $height = $this->getParameter('height-grid');
        $nbPions = 4;
        $moduloMax = 4;
        $moduloMin = 2;
        $idMax = $width * 3;
        for ($i = 0; $i < count($slots); $i++) {
            if ($i % 7 < $moduloMax && $i < $idMax) {
                $players = array();
                for ($j = 0; $j < $long; $j++) {
                    array_push($players, $slots[$i + $j * 8]->getPlayer());
                }
                $array = array_filter($players, function ($value) use ($players) {
                    if ($value == null)
                        return false;
                    return $value == $players[0];
                });
                if (count($array) == $long) {
                    if (isset($results[$players[0]->getId()])) {
                        $results[$players[0]->getId()]++;
                    } else {
                        $results[$players[0]->getId()] = 1;
                    }
                }
            }

            if ($i % 7 > $moduloMin && $i < $idMax) {
                $players = array();
                for ($j = 0; $j < $long; $j++) {
                    array_push($players, $slots[$i + $j * 6]->getPlayer());
                }
                $array = array_filter($players, function ($value) use ($players) {
                    if ($value == null)
                        return false;
                    return $value == $players[0];
                });
                if (count($array) == $long) {
                    if (isset($results[$players[0]->getId()])) {
                        $results[$players[0]->getId()]++;
                    } else {
                        $results[$players[0]->getId()] = 1;
                    }
                }
            }
        }

//        foreach ($slots as $slot) {
//            $players = array();
//            for ($j = 0; $j < $long; $j++) {
//                $var = 'slot' . $j;
//                $$var = $repoSlot->find(($slot->getId() - $j * 6));
//                if ($$var != null)
//                    array_push($players, $$var->getPlayer());
//            }
//            $array = array_filter($players, function ($value) use ($players) {
//                if ($value == null)
//                    return false;
//                return $value == $players[0];
//            });
//            if (count($array) == $long) {
////                dump('win / slot ' . $slot->getId());
//                if (isset($results[$players[0]->getId()])) {
//                    $results[$players[0]->getId()]++;
//                } else {
//                    $results[$players[0]->getId()] = 1;
//                }
//            }
//
//            //----------------------------------------------------
//            $players = array();
//            for ($j = 0; $j < $long; $j++) {
//                $var = 'slot' . $j;
//                $$var = $repoSlot->find(($slot->getId() - $j * 8));
//                if ($$var != null)
//                    array_push($players, $$var->getPlayer());
//            }
//            $array = array_filter($players, function ($value) use ($players) {
//                if ($value == null)
//                    return false;
//                return $value == $players[0];
//            });
////            dump($players);
//            if (count($array) == $long) {
////                dump('win \\ slot ' . $slot->getId());
//                if (isset($results[$players[0]->getId()])) {
//                    $results[$players[0]->getId()]++;
//                } else {
//                    $results[$players[0]->getId()] = 1;
//                }
//            }
//        }

        return $results;

    }

    function retourneRandomIdColonne($grid)
    {
        //Test
        $em = $this->getDoctrine()->getManager();
        $repoPlayer = $em->getRepository('GameBundle:Player');
        $player = $repoPlayer->find(1);


        //-----------
        $colonnes = $grid->getColonnes();
        //return $colonnes[0]->getId();
//        return $this->genereTours($grid, $player, 1)[0];
        $colonne = '';
        do {
            $full = true;
            $colonne = $colonnes[rand(1, count($colonnes))];
            foreach ($colonne->getSlots() as $slot) {
                if ($slot->getPlayer() == null) {
                    $full = false;
                }
            }
        } while ($full);
        return $colonne->getId();

    }

    function getValeurGrid($grid, $player)
    {

        $value = 0;
        //test si gagne ou perdu ou égalité(+1000/-1000/0)
        $result = $this->finJeu($grid);
        if (isset($result['idGagnant'])) {
            if ($result['idGagnant'] == $player->getId()) {
                $value += 1000;
            } else {
                $value -= 1000;
            }
            return $value;
        }

        return $value;

        //test si 3 pions alignés avec libre de chaque coté(+1000)

        //test si 3 pions alignés(+300)

        //test si 2 pions alignés libres de chaque côté(+100)


    }

    /**
     * Genere toutes les grilles de jeu
     *
     * @param $grids
     * @param $depth
     * @return array*
     */
    function genereGrids($grids, $depth)
    {
        if ($depth == 1) {
            $gridsToReturn = array();
            $em = $this->getDoctrine()->getEntityManager();
            $repoGrid = $em->getRepository('GameBundle:Grid');
            $grid = $repoGrid->getHydratedGrid($grids->getId());

            foreach ($grid->getColonnes() as $colonne) {
                $slotToEdit = null;
                foreach ($colonne->getSlots() as $slot) {
                    //Je récupère le dernier slot vide
                    if ($slot->getPlayer() == null) {
                        $slotToEdit = $slot;
                    }
                }
                //Si la colonne n'est pas déjà remplie
                if (is_null($slotToEdit)) {
                    //Je le set
                    $slotToEdit->setPlayer($grid->getNextPlayer());
                    //Je copie ma grille
                    $newGrid = $grid;
                    //J'enregistre ma nouvelle grille générée dans mon tableau a retourner
                    array_push($gridsToReturn, $newGrid);
                    //Je reset mon slot
                    $slotToEdit->setPlayer(null);
                }
            }

            return $gridsToReturn;
        }

        $gridsToReturn = array();
        foreach ($grids as $grid) {
            array_push($gridsToReturn, $this->genereGrids($grid, $depth - 1));
        }

        return $gridsToReturn;
    }

    function genereTours2($grid, $player, $depth)
    {
        dump($grid);
        $em = $this->getDoctrine()->getManager();
        $repoPlayer = $em->getRepository('GameBundle:Player');
        $grids = array();
        $originalGrid = $grid;

        //Je génère tous mes coups

        //parcours des colonnes
//        foreach ($grid->getColonnes() as $colonne) {
        for ($i = 0; $i < count($grid->getColonnes()); $i++) {
            $newGrid = new Grid();
            $newGrid = $originalGrid;
            $colonne = $newGrid->getColonnes()[$i];
            //Je récupère le premier slot vide
            foreach ($colonne->getSlots() as $slot) {
                if ($slot->getPlayer() == null) {
                    $slotToEdit = $slot;
                }
            }
            //Si colonne déja remplie
            if (!isset($slotToEdit)) {
                break;
            }

            //J'edite le slot
            $slotToEdit->setPlayer($player);

            //j'enregistre la nouvelle grille dans l'array
//            array_push($grids, $newGrid);
//            dump($this->getFullSlots($newGrid));
            $grids[$colonne->getId()] = $newGrid;
            dump($this->getCountFullSlots($newGrid));
            $slotToEdit->setPlayer(null);
        }
//        dump($grids);

        //Je trie mon tableau par ordre croissant
        $newGrids = $grids;
//        $newGrids = uasort($grids, function ($a, $b) use ($player) {
//            $aValue = $this->getValeurGrid($a, $player);
//            $bValue = $this->getValeurGrid($b, $player);
//            if ($aValue == $bValue) {
//                return 0;
//            }
//            return ($aValue < $bValue) ? -1 : 1;
//        });
//        dump($newGrids);
        $id = 0;
        $value = -10000;
        $repoSlot = $em->getRepository('GameBundle:Slot');
        foreach ($grids as $key => $gridd) {
            dump($this->getCountFullSlots($gridd));
            $newValue = $this->getValeurGrid($gridd, $player);
            dump($key . '/' . $newValue);
//            dump($gridd);
            if ($newValue > $value) {
                $id = $key;
                $value = $newValue;
            }
        }


        return [$id, $value];

//        if ($player->getId() == 1) {
//            $slotToReturn = '';
//            foreach ($newGrids as $slot => $gridd) {
//                $slotToReturn = $slot;
//            }
//            return $slotToReturn;
//        } else {
//            foreach ($newGrids as $slot => $gridd) {
//                return $slot;
//            }
//        }
    }

    function getFullSlots($grid)
    {
        $fullSlots = array();
        foreach ($grid->getColonnes() as $colonne) {
            foreach ($colonne->getSlots() as $slot) {
                if ($slot->getPlayer() != null) {
                    array_push($fullSlots, $slot->getId());
                }
            }
        }
        return $fullSlots;
    }

    function getCountFullSlots($grid)
    {
        $count = 0;
        foreach ($grid->getColonnes() as $colonne) {
            foreach ($colonne->getSlots() as $slot) {
                if ($slot->getPlayer() != null) {
                    $count++;
                }
            }
        }
        return $count;
    }


}
