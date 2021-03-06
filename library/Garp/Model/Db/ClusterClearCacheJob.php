<?php
/**
* @author David Spreekmeester | grrr.nl
*/

class Garp_Model_Db_ClusterClearCacheJob extends Model_Base_ClusterClearCacheJob {
    public function init() {
        parent::init();

        $this->unregisterObserver('Cachable');
        $this->unregisterObserver('Authorable');
    }

    /**
     * @param Int $serverId Database id of the current server in the cluster
     * @param String $lastCheckIn MySQL datetime that represents the last check-in time of this server
     */
    public function fetchDue($serverId, $lastCheckIn) {
        $select = $this->select()
            ->where('creator_id != ?', $serverId)
            ->where('created >= ?', $lastCheckIn)
        ;

        return $this->fetchAll($select);
    }

    /**
     * @param Int $serverId Database id of the current server in the cluster
     * @param Array $tags Array of tags, for specific cache clearing
     */
    public function create($serverId, array $tags = array()) {
        $row = $this->createRow();

        $row->creator_id = $serverId;
        $row->tags = serialize($tags);

        $row->save();
    }

    /**
     * Clean records older than 2 days.
     */
    public function deleteOld() {
        return $this->delete(
            "created < DATE_SUB(NOW(), INTERVAL 2 DAY)"
        );
    }
}
