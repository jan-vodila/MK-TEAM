<?php

namespace App\Presenters;

use Nette,
    App\Model,
    Nette\Application\UI\Form;

class AdminPresenter extends BasePresenter {

    private $userRepository, $classRepository, $testRepository;

    protected function startup() {
	parent::startup();
	$this->userRepository = $this->context->userRepository;
        $this->classRepository = $this->context->classRepository;
        $this->testRepository = $this->context->testRepository;
        
        if ($this->user->isLoggedIn()) {
	    if ($this->user->isInRole(Model\UserRepository::ADMIN)) {
		$this->redirect('Admin:');
	    }
	} else {
	    $this->redirect('Auth:');
	}
    }

    public function actionDefault() {
	
    }

    public function actionNewTask($test) {
	if (!$this->getRequest()->isPost()) {
	    if ($test == 1) {
		$test_row = $this->testRepository->getTestForUser($this->user->getId());
		if ($this->testRepository->getFilledTaskInTest($test_row->id, $this->user->getId())) {
		    $this->flashMessage('Už ste vyplnili test', self::FLASH_MESSAGE_DANGER);
		    $this->redirect('Student:');
		} else if ($tasks = $this->testRepository->getUnfilledTaskInTest($test_row->id, $this->user->getId())) {
		    $this->unFilledTasks = array();
		    foreach ($tasks as $value) {
			$this->unFilledTasks[] = $this->unitConversion->reGenerateTask($value);
		    }
		}
		$this->numberOfTasks = $test_row->nb_count;
		$this->difficulty = $test_row->nb_level;
		$this->test_id = $test_row->id;
		$this->template->form = $this['newTaskForm'];
		$this->template->tasks = $this->tasks;
		$this->template->unitConversion = $this->unitConversion;
	    } else {
		$this->template->form = $this['newTaskForm'];
		$this->template->tasks = $this->tasks;
		$this->template->unitConversion = $this->unitConversion;
	    }
	}
    }

    public function actionTest() {
	if ($this->testRepository->getTestForUser($this->user->getId())->id == NULL) {
	    $this->flashMessage('Momentálne pre Vás neexistuje test', self::FLASH_MESSAGE_WARNING);
	    $this->redirect('Student:');
	} else {
	    $this->redirect('Student:newTask', 1);
	}
    }

    protected function createComponentNewTaskForm() {
	$this->tasks = array();

	$form = new Form;
	$form->getElementPrototype()->class('form-horizontal task-list');
	if (!$this->getRequest()->isPost()) {
	    for ($i = 0; $i < $this->numberOfTasks; $i++) {
		if ($this->unFilledTasks) {
		    $singleTask = $this->unFilledTasks[$i];
		} else {
		    $singleTask = $this->unitConversion->generateConversion($this->user->getId(), $this->difficulty, $this->test_id);
		}
		$this->tasks[$singleTask->getId()] = $singleTask;

		$singleTaskInput = $form->addText("task" . $singleTask->getId(), $singleTask . " " . $singleTask->getUnitName());
		$singleTaskInput->addCondition(Form::FILLED)->addRule(Form::FLOAT, "Základný tvar " . ($i + 1) . ". príkladu má neplatný číselný zápis");

		$singleTaskInput->getLabelPrototype()->setHtml($singleTaskInput->getLabelPrototype()->getHtml() . "<span class='equal-to'>=</span>");
		$singleTaskInput->getLabelPrototype()->class = 'control-label';
		$singleTaskInput->setAttribute('class', 'form-control input-sm base-number-format-input');
		$singleTaskInput->setAttribute('placeholder', 'Zákl. tvar');
		$form->addText("taskExp" . $singleTask->getId())->setAttribute('class', 'form-control input-sm')
			->addCondition(Form::FILLED)->addRule(Form::FLOAT, "Prvý exponent " . ($i + 1) . ". príkladu má neplatný číselný zápis");
		$form->addText("taskBaseExp" . $singleTask->getId())->setAttribute('class', 'form-control input-sm')
			->addCondition(Form::FILLED)->addRule(Form::FLOAT, "Druhý exponent " . ($i + 1) . ". príkladu má neplatný číselný zápis");
	    }
	    $form->addSubmit("send", "Vyhodnotiť")->setAttribute('class', 'btn btn-primary');
	}
	$form->onSuccess[] = $this->taskFormSubmitted;

	return $form;
    }

    public function taskFormSubmitted($form, $values) {
	$this->tasks = array();
	$values = $form->getHttpData();
	foreach ($values as $key => $value) {
	    if (preg_match("/^task([0-9]+)$/", $key, $matches) > 0) {
		$data['value'] = floatval($value);
		$data['exp'] = (array_key_exists('taskExp' . $matches[1], $values)) ? intval($values['taskExp' . $matches[1]]) : 0;
		$data['expBase'] = (array_key_exists('taskBaseExp' . $matches[1], $values)) ? intval($values['taskBaseExp' . $matches[1]]) : 0;
		if ($this->unitConversion->checkConversion($this->user->getId(), $matches[1], $data)) {
		    $this->tasks[] = $this->unitConversion->getTask($matches[1]);
		}
	    }
	}
	$this->setView('showResult');
    }

    public function renderShowResult() {
	$this->template->tasks = $this->tasks;
	$this->template->unitConversion = $this->unitConversion;
    }

}
