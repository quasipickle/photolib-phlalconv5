<?php

namespace Controller;

use Component\Retval;
use Model\{Album, AlbumPhoto, Photo};

class BattleController extends BaseController
{
    public function indexAction()
    {
        if ($this->request->isPost()) {
            $winnerId = $this->request->getPost("winnerId");
            $loserId = $this->request->getPost("loserId");

            $Winner = Photo::findFirst($winnerId);
            if ($Winner != null) {
                $Winner->wins = $Winner->wins + 1;
                $Winner->battles = $Winner->battles + 1;
                $Winner->save();
            }
            $Loser = Photo::findFirst($loserId);
            if ($Loser != null) {
                $Loser->battles = $Loser->battles + 1;
                $Loser->save();
            }
            $Response = new \Phalcon\Http\Response();
            return $Response->redirect("/battle", false, 302);
        }
        $this->view->title = "Battle";
        $this->view->photos = Photo::find(["order" => "RAND()", "limit" => 2]);
    }

    /**
     * This is called by AJAX, and the rendered output injected into the calling page
     * @return string
     */
    public function statsAction()
    {
        $Builder = new \Phalcon\Mvc\Model\Query\Builder();
        // Photo rate count distribution
        $distributionResult = $Builder
            ->from([
                "photo" => Photo::class
            ])
            ->columns([
                "photo.battles",
                "count" => "COUNT(*)"
            ])
            ->groupBy("photo.battles")
            ->orderBy("photo.battles")
            ->getQuery()
            ->execute();
        $distribution = [];
        foreach ($distributionResult as $row) {
            $distribution[$row["battles"]] = $row["count"];
        }
        for ($i = min(array_keys($distribution)); $i < max(array_keys($distribution)); $i++) {
            if (!array_key_exists($i, $distribution)) {
                $distribution[$i] = 0;
            }
        }
        $this->view->distribution = $distribution;
        $this->view->distributionMax = max($distribution);

        $Builder = new \Phalcon\Mvc\Model\Query\Builder();
        $photoResult = $Builder
            ->from([
                "photo" => Photo::class
            ])
            ->where("battles > 1")
            ->orderBy("wins/battles DESC, battles DESC")
            ->limit(10)
            ->getQuery()
            ->execute();
        $this->view->popularPhotos = $photoResult;

        $Builder = new \Phalcon\Mvc\Model\Query\Builder();
        $albumResult = $Builder
            ->columns([
                "a.name",
                "pf.thumb_path",
                "photoWins" => "SUM(p.wins)",
                "photoBattles" => "SUM(p.battles)",
                "success" => "SUM(p.wins) / SUM(p.battles)"
            ])
            ->from([
                "pf" => Photo::class
            ])
            ->leftJoin(Album::class, "pf.id = a.photo_id", "a")
            ->leftJoin(AlbumPhoto::class, "a.id = ap.album_id", "ap")
            ->leftJoin(Photo::class, "ap.photo_id = p.id", "p")
            ->groupBy("a.id")
            ->orderBy("success DESC, photoBattles DESC")
            ->limit(10)
            ->getQuery()
            ->execute();

        $this->view->popularAlbums = $albumResult;
        $this->view->disableLevel(\Phalcon\Mvc\View::LEVEL_MAIN_LAYOUT);

        $this->view->start();
        $this->view->render("battle", "stats");
        $this->view->finish();

        $Retval = new Retval();
        return $Retval->success(true)->content($this->view->getContent())->response();
    }
}