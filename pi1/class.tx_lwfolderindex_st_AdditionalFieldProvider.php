<?php
class tx_lwfolderindex_st_AdditionalFieldProvider implements tx_scheduler_AdditionalFieldProvider {
    public function getAdditionalFields(array &$taskInfo, $task, tx_scheduler_Module $parentObject) {
        
        if (empty($taskInfo['rootdir'])) {
                if($parentObject->CMD == 'edit') {
                        $taskInfo['rootdir'] = $task->rootdir;
                } else {
                        $taskInfo['rootdir'] = '';
                }
        }

        // Write the code for the field
        $fieldID = 'task_rootdir';
        $fieldCode = '<input type="text" name="tx_scheduler[rootdir]" id="' . $fieldID . '" value="' . $taskInfo['rootdir'] . '" size="30" />';
        $additionalFields = array();
        $additionalFields[$fieldID] = array(
                'code'     => $fieldCode,
                'label'    => 'Root directory (relative to root)'
        );

        return $additionalFields;

    }

    public function validateAdditionalFields(array &$submittedData, tx_scheduler_Module $parentObject) {
        
        $submittedData['rootdir'] = trim($submittedData['rootdir']);
        return true;
    }

    public function saveAdditionalFields(array $submittedData, tx_scheduler_Task $task) {
        $task->rootdir = $submittedData['rootdir'];
    }
}
?>