<?php

    /* IndexWelcomeModel class in index module */
    class IndexWelcomeModel extends AeoModel
    {
        public function getMessage()
        {
            Aeolus::loadClass('AeoCache');

            if (!$data = AeoCache::fetch('date')) {
                AeoCache::store(
                    'Message cached at ' . date('Y-m-d H:i:s', time()) . ' for 5 minutes.',
                    'date'
                );
            }

            return $data;
        }
    }
?>
