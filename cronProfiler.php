<?php

require_once './app/Mage.php';
Mage::init();

class CronProfiler
{
    protected $_salt;
    protected $_logFile;
    
    public function __construct()
    {
        $this->_salt = time();
        $this->_logFile = 'cron_profiler.' . $this->_salt . '.log';
    }
    
    protected function _formatTime($time)
    {
        return sprintf('%02d:%02d:%02d', ($time / 3600), ($time / 60 % 60), $time % 60);
    }

    protected function _p($string = '')
    {
        echo $string . "\n";
        Mage::log($string, null, $this->_logFile, true);
        return $this;
    }

    protected function _runJob($jobData = array())
    {
        if (empty($jobData['run']['model'])) {
            return array(
                'duration' => 0,
                'message' => 'run expression is missed'
            );
        }
        $runExpr = $jobData['run']['model'];
        list($jobModel, $jobMethod) = explode('::', $runExpr);

        $message = '';
        $jobStart = microtime(true);

        try {
            /** @var Mage_Core_Model_Abstract $model */
            $model = Mage::getModel($jobModel);
            $model->$jobMethod();
        } catch (Exception $e) {
            $message = $e->getMessage();
        }
        $jobEnd = microtime(true);

        $duration = $this->_formatTime($jobEnd - $jobStart);

        return array(
            'duration' => $duration,
            'message' => $message
        );

    }
    
    public function run()
    {
        $this->_p('Magento cron jobs:');
        $jobs = Mage::getConfig()->getNode('crontab/jobs')->asArray();
        foreach ($jobs as $jobCode => $jobData) {
            $scheduledAt = (!empty($jobData['schedule']['cron_expr'])) 
                ? $jobData['schedule']['cron_expr'] : '__EMPTY__';
            $this->_p('    - [' . $scheduledAt . '] ' . $jobCode . ' ' . $jobData['run']['model']);
        }
        $this->_p("\n");
        
        $startTime = microtime(true);

        foreach ($jobs as $jobCode => $jobData) {
            $r = $this->_runJob($jobData);
            $this->_p('[' . $r['duration'] . '] ' . $jobCode . ' was executed ' 
                . (empty($r['message']) ? 'successfully' : 'with message: ' . $r['message']));
        }
        $this->_p("\n");

        $endTime = microtime(true);
        $summaryTime = $this->_formatTime($endTime - $startTime);
        
        $this->_p('TOTAL: ' . count($jobs) .' jobs were executed in ' . $summaryTime);
    }
}

$cronProfiler = new CronProfiler;
$cronProfiler->run();
