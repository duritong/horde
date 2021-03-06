<?php
/**
 * Nag_Task handles as single task as well as a list of tasks and implements a
 * recursive iterator to handle a (hierarchical) list of tasks.
 *
 * See the enclosed file COPYING for license information (GPL). If you
 * did not receive this file, see http://www.horde.org/licenses/gpl.
 *
 * @author  Jan Schneider <jan@horde.org>
 * @package Nag
 *
 * @property tags array  An array of tags this task is tagged with.
 */
class Nag_Task
{
    /**
     * The task id.
     *
     * @var string
     */
    public $id;

    /**
     * This task's tasklist id.
     *
     * @var string
     */
    public $tasklist;

    /**
     * The task uid.
     *
     * @var string
     */
    public $uid;

    /**
     * The task owner.
     *
     * @var string
     */
    public $owner;

    /**
     * The task assignee.
     *
     * @var string
     */
    public $assignee;

    /**
     * The task title.
     *
     * @var string
     */
    public $name;

    /**
     * The task decription.
     *
     * @var string
     */
    public $desc;

    /**
     * The start date timestamp.
     *
     * @var integer
     */
    public $start;

    /**
     * The due date timestamp.
     *
     * @var integer
     */
    public $due;

    /**
     * Recurrence rules for recurring tasks.
     *
     * @var Horde_Date_Recurrence
     */
    public $recurrence;

    /**
     * The task priority.
     *
     * @var integer
     */
    public $priority;

    /**
     * The estimated task length.
     *
     * @var float
     */
    public $estimate;

    /**
     * Whether the task is completed.
     *
     * @var boolean
     */
    public $completed;

    /**
     * The completion date timestamp.
     *
     * @var integer
     */
    public $completed_date;

    /**
     * The task alarm threshold.
     *
     * @var integer
     */
    public $alarm;

    /**
     * The particular alarm methods overridden for this task.
     *
     * @var array
     */
    public $methods;

    /**
     * Whether the task is private.
     *
     * @var boolean
     */
    public $private;

    /**
     * URL to view the task.
     *
     * @var string
     */
    public $view_link;

    /**
     * URL to complete the task.
     *
     * @var string
     */
    public $complete_link;

    /**
     * URL to edit the task.
     *
     * @var string
     */
    public $edit_link;

    /**
     * URL to delete the task.
     *
     * @var string
     */
    public $delete_link;

    /**
     * The parent task's id.
     *
     * @var string
     */
    public $parent_id = '';

    /**
     * The parent task.
     *
     * @var Nag_Task
     */
    public $parent;

    /**
     * The sub-tasks.
     *
     * @var array
     */
    public $children = array();

    /**
     * This task's idention (child) level.
     *
     * @var integer
     */
    public $indent = 0;

    /**
     * Whether this is the last sub-task.
     *
     * @var boolean
     */
    public $lastChild;

    /**
     * A storage driver.
     *
     * @var Nag_Driver
     */
    protected $_storage;

    /**
     * Internal flag.
     *
     * @var boolean
     * @see each()
     */
    protected $_inlist = false;

    /**
     * Internal pointer.
     *
     * @var integer
     * @see each()
     */
    protected $_pointer;

    /**
     * Task id => pointer dictionary.
     *
     * @var array
     */
    protected $_dict = array();

    /**
     * Task tags (lazy loaded).
     *
     * @var array
     */
    protected $_tags;

    /**
     * Constructor.
     *
     * Takes a hash and returns a nice wrapper around it.
     *
     * @param Nag_Driver $storage  A storage driver.
     * @param array $task          A task hash.
     */
    public function __construct(Nag_Driver $storage = null, array $task = null)
    {
        if ($storage) {
            $this->_storage = $storage;
        }
        if ($task) {
            $this->merge($task);
        }
    }

    /**
     * Getter.
     *
     * Returns the 'id' and 'creator' properties.
     *
     * @param string $name  Property name.
     *
     * @return mixed  Property value.
     */
    public function __get($name)
    {
        switch ($name) {

        case 'tags':
            if (!isset($this->_tags)) {
                $this->_tags = Nag::getTagger()->getTags($this->uid, 'task');
            }
            return $this->_tags;
        }

        $trace = debug_backtrace();
        trigger_error('Undefined property via __set(): ' . $name
                      . ' in ' . $trace[0]['file']
                      . ' on line ' . $trace[0]['line'],
                      E_USER_NOTICE);
        return null;
    }

    /**
     * Setter.
     *
     * @param string $name  Property name.
     * @param mixed $value  Property value.
     */
    public function __set($name, $value)
    {
        switch ($name) {
        case 'tags':
            $this->{'_' . $name} = $value;
            return;
        }
        $trace = debug_backtrace();
        trigger_error('Undefined property via __set(): ' . $name
                      . ' in ' . $trace[0]['file']
                      . ' on line ' . $trace[0]['line'],
                      E_USER_NOTICE);
    }

    /**
     * Merges a task hash into this task object.
     *
     * @param array $task  A task hash.
     */
    public function merge(array $task)
    {
        foreach ($task as $key => $val) {
            switch ($key) {
            case 'tasklist_id':
                $key = 'tasklist';
                break;
            case 'task_id':
                $key = 'id';
                break;
            case 'parent':
                $key = 'parent_id';
                break;
            }
            $this->$key = $val;
        }
    }

    /**
     * Saves this task in the storage backend.
     *
     * @throws Nag_Exception
     */
    public function save()
    {
        $this->_storage->modify($this->id, $this->toHash(true));
    }

    /**
     * Returns the parent task of this task, if one exists.
     *
     * @return mixed  The parent task, null if none exists
     */
    public function getParent()
    {
        if (!$this->parent_id) {
            return null;
        }
        return Nag::getTask($this->tasklist, $this->parent_id);
    }

    /**
     * Adds a sub task to this task.
     *
     * @param Nag_Task $task  A sub task.
     */
    public function add(Nag_Task $task)
    {
        if (!isset($this->_dict[$task->id])) {
            $this->_dict[$task->id] = count($this->children);
            $task->parent = $this;
            $this->children[] = $task;
        }
    }

    /**
     * Loads all sub-tasks.
     */
    public function loadChildren()
    {
        try {
            $this->children = $this->_storage->getChildren($this->id);
        } catch (Nag_Exception $e) {}
    }

    /**
     * Merges an array of tasks into this task's children.
     *
     * @param array $children  A list of Nag_Tasks.
     *
     */
    public function mergeChildren(array $children)
    {
        for ($i = 0, $c = count($children); $i < $c; ++$i) {
            $this->add($children[$i]);
        }
    }

    /**
     * Returns a sub task by its id.
     *
     * The methods goes recursively through all sub tasks until it finds the
     * searched task.
     *
     * @param string $key  A task id.
     *
     * @return Nag_Task  The searched task or null.
     */
    public function get($key)
    {
        return isset($this->_dict[$key]) ?
            $this->children[$this->_dict[$key]] :
            null;
    }

    /**
     * Returns whether this is a task (not a container) or contains any sub
     * tasks.
     *
     * @return boolean  True if this is a task or has sub tasks.
     */
    public function hasTasks()
    {
        return ($this->id) ? true : $this->hasSubTasks();
    }

    /**
     * Returns whether this task contains any sub tasks.
     *
     * @return boolean  True if this task has sub tasks.
     */
    public function hasSubTasks()
    {
        foreach ($this->children as $task) {
            if ($task->hasTasks()) {
                return true;
            }
        }
        return false;
    }

    /**
     * Returns whether all sub tasks are completed.
     *
     * @return boolean  True if all sub tasks are completed.
     */
    public function childrenCompleted()
    {
        foreach ($this->children as $task) {
            if (!$task->completed || !$task->childrenCompleted()) {
                return false;
            }
        }
        return true;
    }

    /**
     * Returns the number of tasks including this and any sub tasks.
     *
     * @return integer  The number of tasks and sub tasks.
     */
    public function count()
    {
        $count = $this->id ? 1 : 0;
        foreach ($this->children as $task) {
            $count += $task->count();
        }
        return $count;
    }

    /**
     * Returns the estimated length for this and any sub tasks.
     *
     * @return integer  The estimated length sum.
     */
    public function estimation()
    {
        $estimate = $this->estimate;
        foreach ($this->children as $task) {
            $estimate += $task->estimation();
        }
        return $estimate;
    }

    /**
     * Returns whether this task is a recurring task.
     *
     * @return boolean  True if this is a recurring task.
     */
    public function recurs()
    {
        return isset($this->recurrence) &&
            !$this->recurrence->hasRecurType(Horde_Date_Recurrence::RECUR_NONE);
    }

    /**
     * Toggles completion status of this task. Moves a recurring task
     * to the next occurence on completion.
     */
    public function toggleComplete()
    {
        if ($this->completed) {
            $this->completed_date = null;
            $this->completed = false;
            if ($this->recurs()) {
                /* What do we want do delete here? All completions?
                 * The latest completion? Any completion in the
                 * future?. */
                foreach ($this->recurrence->getCompletions() as $completion) {
                    $this->recurrence->deleteCompletion(
                        substr($completion, 0, 4),
                        substr($completion, 4, 2),
                        substr($completion, 6, 2));
                }
            }
            return;
        }

        if ($this->recurs()) {
            /* Get current occurrence (task due date) */
            $current = $this->recurrence->nextActiveRecurrence(new Horde_Date($this->due));
            if ($current) {
                $this->recurrence->addCompletion($current->year,
                                                 $current->month,
                                                 $current->mday);
                /* Advance this occurence by a day to indicate that we
                 * want the following occurence (Recurrence uses days
                 * as minimal time duration between occurrences). */
                $current->mday++;
                /* Only mark this due date completed if there is another
                 * occurence. */
                if ($this->recurrence->nextActiveRecurrence($current)) {
                    $this->completed = false;
                    return;
                }
            }
        }

        $this->completed_date = time();
        $this->completed = true;
    }

    /**
     * Returns the next due date of this task.
     *
     * Takes recurring tasks into account.
     *
     * @return Horde_Date  The next due date.
     */
    public function getNextDue()
    {
        if (!$this->due) {
            return null;
        }
        if (!$this->recurs()) {
            return new Horde_Date($this->due);
        }
        return $this->recurrence->nextActiveRecurrence($this->due);
    }

    /**
     * Format the description - link URLs, etc.
     *
     * @return string
     */
    public function getFormattedDescription()
    {
        $desc = $GLOBALS['injector']
            ->getInstance('Horde_Core_Factory_TextFilter')
            ->filter($this->desc,
                     'text2html',
                     array('parselevel' => Horde_Text_Filter_Text2html::MICRO));
        try {
            return Horde::callHook('format_description', array($desc), 'nag');
        } catch (Horde_Exception_HookNotSet $e) {
            return $desc;
        }
    }

    /**
     * Resets the tasks iterator.
     *
     * Call this each time before looping through the tasks.
     *
     * @see each()
     */
    public function reset()
    {
        foreach (array_keys($this->children) as $key) {
            $this->children[$key]->reset();
        }
        $this->_pointer = 0;
        $this->_inlist = false;
    }

    /**
     * Returns the next task iterating through all tasks and sub tasks.
     *
     * Call reset() each time before looping through the tasks:
     * <code>
     * $tasks->reset();
     * while ($task = $tasks->each() {
     *     ...
     * }
     *
     * @see reset()
     */
    public function each()
    {
        if ($this->id && !$this->_inlist) {
            $this->_inlist = true;
            return $this;
        }
        if ($this->_pointer >= count($this->children)) {
            $task = false;
            return $task;
        }
        $next = $this->children[$this->_pointer]->each();
        if ($next) {
            return $next;
        }
        $this->_pointer++;
        return $this->each();
    }

    /**
     * Processes a list of tasks by adding action links, obscuring details of
     * private tasks and calculating indentation.
     *
     * @param integer $indent  The indention level of the tasks.
     */
    public function process($indent = 0)
    {
        /* Link cache. */
        static $view_url_list, $task_url_list;

        /* Set indention. */
        $this->indent = $indent;
        if ($this->id) {
            ++$indent;
        }

        /* Process children. */
        for ($i = 0, $c = count($this->children); $i < $c; ++$i) {
            $this->children[$i]->process($indent);
        }

        /* Mark last child. */
        if (count($this->children)) {
            $this->children[count($this->children) - 1]->lastChild = true;
        }

        /* Only process further if this is really a (parent) task, not only a
         * task list container. */
        if (!$this->id) {
            return;
        }

        if (!isset($view_url_list[$this->tasklist])) {
            $view_url_list[$this->tasklist] = Horde_Util::addParameter(Horde::url('view.php'), 'tasklist', $this->tasklist);
            $task_url_list[$this->tasklist] = Horde_Util::addParameter(Horde::url('task.php'), 'tasklist', $this->tasklist);
        }

        /* Obscure private tasks. */
        if ($this->private && $this->owner != $GLOBALS['registry']->getAuth()) {
            $this->name = _("Private Task");
            $this->desc = '';
        }

        /* Create task links. */
        $this->view_link = Horde_Util::addParameter($view_url_list[$this->tasklist], 'task', $this->id);

        $task_url_task = Horde_Util::addParameter($task_url_list[$this->tasklist], 'task', $this->id);
        $this->complete_link = Horde::url('t/complete')->add(array('url' => Horde::url('list.php'), 'task' => $this->id, 'tasklist' => $this->tasklist));
        $this->edit_link = Horde_Util::addParameter($task_url_task, 'actionID', 'modify_task');
        $this->delete_link = Horde_Util::addParameter($task_url_task, 'actionID', 'delete_task');
    }

    /**
     * Returns the HTML code for any tree icons, when displaying this task in
     * a tree view.
     *
     * @return string  The HTML code for necessary tree icons.
     */
    public function treeIcons()
    {
        $html = '';

        $parent = $this->parent;
        for ($i = 1; $i < $this->indent; ++$i) {
            if ($parent && $parent->lastChild) {
                $html = Horde::img('tree/blank.png') . $html;
            } else {
                $html = Horde::img('tree/line.png', '|') . $html;
            }
            $parent = $parent->parent;
        }
        if ($this->indent) {
            if ($this->lastChild) {
                $html .= Horde::img($GLOBALS['registry']->nlsconfig->curr_rtl ? 'tree/rev-joinbottom.png' : 'tree/joinbottom.png', '\\');
            } else {
                $html .= Horde::img($GLOBALS['registry']->nlsconfig->curr_rtl ? 'tree/rev-join.png' : 'tree/join.png', '+');
            }
        }

        return $html;
    }

    /**
     * Recursively loads tags for all tasks contained in this object.
     */
    public function loadTags()
    {
        $ids = array($this->uid);
        foreach ($this->children as $task) {
            $ids[] = $task->uid;
        }
        $results = Nag::getTagger()->getTags($ids);

        foreach ($this->children as $task) {
            if (!empty($results[$task->uid])) {
                $task->tags = $results[$task->uid];
            }
        }
        if (!empty($results[$this->uid])) {
            $this->_tags = $results[$this->uid];
        }
    }

    /**
     * Sorts sub tasks by the given criteria.
     *
     * @param string $sortby     The field by which to sort
     *                           (Nag::SORT_PRIORITY, Nag::SORT_NAME
     *                           Nag::SORT_DUE, Nag::SORT_COMPLETION).
     * @param integer $sortdir   The direction by which to sort
     *                           (Nag::SORT_ASCEND, Nag::SORT_DESCEND).
     * @param string $altsortby  The secondary sort field.
     */
    public function sort($sortby, $sortdir, $altsortby)
    {
        /* Sorting criteria for the task list. */
        $sort_functions = array(
            Nag::SORT_PRIORITY => 'ByPriority',
            Nag::SORT_NAME => 'ByName',
            Nag::SORT_DUE => 'ByDue',
            Nag::SORT_START => 'ByStart',
            Nag::SORT_COMPLETION => 'ByCompletion',
            Nag::SORT_ASSIGNEE => 'ByAssignee',
            Nag::SORT_ESTIMATE => 'ByEstimate',
            Nag::SORT_OWNER => 'ByOwner'
        );

        /* Sort the array if we have a sort function defined for this
         * field. */
        if (isset($sort_functions[$sortby])) {
            $prefix = ($sortdir == Nag::SORT_DESCEND) ? '_rsort' : '_sort';
            usort($this->children, array('Nag', $prefix . $sort_functions[$sortby]));
            if (isset($sort_functions[$altsortby]) && $altsortby !== $sortby) {
                $task_buckets = array();
                for ($i = 0, $c = count($this->children); $i < $c; ++$i) {
                    if (!isset($task_buckets[$this->children[$i]->$sortby])) {
                        $task_buckets[$this->children[$i]->$sortby] = array();
                    }
                    $task_buckets[$this->children[$i]->$sortby][] = $this->children[$i];
                }
                $tasks = array();
                foreach ($task_buckets as $task_bucket) {
                    usort($task_bucket, array('Nag', $prefix . $sort_functions[$altsortby]));
                    $tasks = array_merge($tasks, $task_bucket);
                }
                $this->children = $tasks;
            }

            /* Mark last child. */
            for ($i = 0, $c = count($this->children); $i < $c; ++$i) {
                $this->children[$i]->lastChild = false;
            }
            if (count($this->children)) {
                $this->children[count($this->children) - 1]->lastChild = true;
            }

            for ($i = 0, $c = count($this->children); $i < $c; ++$i) {
                $this->_dict[$this->children[$i]->id] = $i;
                $this->children[$i]->sort($sortby, $sortdir, $altsortby);
            }
        }
    }

    /**
     * Returns a hash representation for this task.
     *
     * @return array  A task hash.
     */
    public function toHash()
    {
        $hash = array(
            'tasklist_id' => $this->tasklist,
            'task_id' => $this->id,
            'uid' => $this->uid,
            'parent' => $this->parent_id,
            'owner' => $this->owner,
            'assignee' => $this->assignee,
            'name' => $this->name,
            'desc' => $this->desc,
            'start' => $this->start,
            'due' => $this->due,
            'priority' => $this->priority,
            'estimate' => $this->estimate,
            'completed' => $this->completed,
            'completed_date' => $this->completed_date,
            'alarm' => $this->alarm,
            'methods' => $this->methods,
            'private' => $this->private,
            'recurrence' => $this->recurrence,
            'tags' => $this->tags);

        return $hash;
    }

    /**
     * Returns a simple object suitable for json transport representing this
     * task.
     *
     * @param boolean $full        Whether to return all task details.
     * @param string $time_format  The date() format to use for time formatting.
     *
     * @return object  A simple object.
     */
    public function toJson($full = false, $time_format = 'H:i')
    {
        $json = new stdClass;
        $json->l = $this->tasklist;
        $json->n = $this->name;
        if ($this->desc) {
            //TODO: Get the proper amount of characters, and cut by last
            //whitespace
            $json->sd = Horde_String::substr($this->desc, 0, 80);
        }
        $json->cp = (boolean)$this->completed;
        if ($this->due && ($due = $this->getNextDue())) {
            $json->du = $due->toJson();
        }
        if ($this->start) {
            $date = new Horde_Date($this->start);
            $json->s = $date->toJson();
        }
        $json->pr = (int)$this->priority;
        if ($this->recurs()) {
            $json->r = $this->recurrence->getRecurType();
        }
        $json->t = array_values($this->tags);

        if ($full) {
            // @todo: do we really need all this?
            $json->id = $this->id;
            $json->de = $this->desc;
            if ($this->due) {
                $date = new Horde_Date($this->due);
                $json->dd = $date->strftime('%x');
                $json->dt = $date->format($time_format);
            }
            /*
            $json->p = $this->parent_id;
            $json->o = $this->owner;
            $json->as = $this->assignee;
            if ($this->estimate) {
                $date = new Horde_Date($this->estimate);
                $json->e = $date->toJson();
            }
            if ($this->completed_date) {
                $date = new Horde_Date($this->completed_date);
                $json->cd = $date->toJson();
            }
            */
            $json->a = (int)$this->alarm;
            $json->m = $this->methods;
            //$json->pv = (boolean)$this->private;
            if ($this->recurs()) {
                $json->r = $this->recurrence->toJson();
            }

            try {
                $share = $GLOBALS['nag_shares']->getShare($this->tasklist);
            } catch (Horde_Share_exception $e) {
                Horde::logMessage($e->getMessage(), 'ERR');
                throw new Nag_Exception($e);
            }
            $json->pe = $share->hasPermission($GLOBALS['registry']->getAuth(), Horde_Perms::EDIT);
            $json->pd = $share->hasPermission($GLOBALS['registry']->getAuth(), Horde_Perms::DELETE);
        }

        return $json;
    }

    /**
     * Returns an alarm hash of this task suitable for Horde_Alarm.
     *
     * @param string $user  The user to return alarms for.
     * @param Prefs $prefs  A Prefs instance.
     *
     * @return array  Alarm hash or null.
     */
    public function toAlarm($user = null, $prefs = null)
    {
        if (empty($this->alarm) || $this->completed) {
            return;
        }

        if (empty($user)) {
            $user = $GLOBALS['registry']->getAuth();
        }
        if (empty($prefs)) {
            $prefs = $GLOBALS['prefs'];
        }

        $methods = !empty($this->methods) ? $this->methods : @unserialize($prefs->getValue('task_alarms'));
        if (!$methods) {
            $methods = array();
        }

        if (isset($methods['notify'])) {
            $methods['notify']['show'] = array(
                '__app' => $GLOBALS['registry']->getApp(),
                'task' => $this->id,
                'tasklist' => $this->tasklist);
            $methods['notify']['ajax'] = 'task:' . $this->tasklist . ':' . $this->id;
            if (!empty($methods['notify']['sound'])) {
                if ($methods['notify']['sound'] == 'on') {
                    // Handle boolean sound preferences;
                    $methods['notify']['sound'] = (string)Horde_Themes::sound('theetone.wav');
                } else {
                    // Else we know we have a sound name that can be
                    // served from Horde.
                    $methods['notify']['sound'] = (string)Horde_Themes::sound($methods['notify']['sound']);
                }
            }
        }
        if (isset($methods['mail'])) {
            $image = Nag::getImagePart('big_alarm.png');

            $view = new Horde_View(array('templatePath' => NAG_TEMPLATES . '/alarm', 'encoding' => 'UTF-8'));
            new Horde_View_Helper_Text($view);
            $view->task = $this;
            $view->imageId = $image->getContentId();
            $view->due = new Horde_Date($this->due);
            $view->dateFormat = $prefs->getValue('date_format');
            $view->timeFormat = $prefs->getValue('twentyFour') ? 'H:i' : 'h:ia';
            if (!$prefs->isLocked('task_alarms')) {
                $view->prefsUrl = Horde::url($GLOBALS['registry']->getServiceLink('prefs', 'nag'), true)->remove(session_name());
            }

            $methods['mail']['mimepart'] = Nag::buildMimeMessage($view, 'mail', $image);
        }
        return array(
            'id' => $this->uid,
            'user' => $user,
            'start' => new Horde_Date($this->due - $this->alarm * 60),
            'methods' => array_keys($methods),
            'params' => $methods,
            'title' => $this->name,
            'text' => $this->desc);
    }

    /**
     * Exports this task in iCalendar format.
     *
     * @param Horde_Icalendar $calendar  A Horde_Icalendar object that acts as
     *                                   the container.
     *
     * @return Horde_Icalendar_Vtodo  A vtodo component of this task.
     */
    public function toiCalendar(Horde_Icalendar $calendar)
    {
        $vTodo = Horde_Icalendar::newComponent('vtodo', $calendar);
        $v1 = $calendar->getAttribute('VERSION') == '1.0';

        $vTodo->setAttribute('UID', $this->uid);

        if (!empty($this->assignee)) {
            $vTodo->setAttribute('ORGANIZER', $this->assignee);
        }

        if (!empty($this->name)) {
            $vTodo->setAttribute('SUMMARY', $this->name);
        }

        if (!empty($this->desc)) {
            $vTodo->setAttribute('DESCRIPTION', $this->desc);
        }

        if (isset($this->priority)) {
            $vTodo->setAttribute('PRIORITY', $this->priority);
        }

        if (!empty($this->parent_id)) {
            $vTodo->setAttribute('RELATED-TO', $this->parent->uid);
        }

        if ($this->private) {
            $vTodo->setAttribute('CLASS', 'PRIVATE');
        }

        if (!empty($this->start)) {
            $vTodo->setAttribute('DTSTART', $this->start);
        }

        if ($this->due) {
            $vTodo->setAttribute('DUE', $this->due);

            if ($this->alarm) {
                if ($v1) {
                    $vTodo->setAttribute('AALARM', $this->due - $this->alarm * 60);
                } else {
                    $vAlarm = Horde_Icalendar::newComponent('valarm', $vTodo);
                    $vAlarm->setAttribute('ACTION', 'DISPLAY');
                    $vAlarm->setAttribute('TRIGGER;VALUE=DURATION', '-PT' . $this->alarm . 'M');
                    $vTodo->addComponent($vAlarm);
                }
            }
        }

        if ($this->completed) {
            $vTodo->setAttribute('STATUS', 'COMPLETED');
            $vTodo->setAttribute('COMPLETED', $this->completed_date ? $this->completed_date : time());
        } else {
            if ($v1) {
                $vTodo->setAttribute('STATUS', 'NEEDS ACTION');
            } else {
                $vTodo->setAttribute('STATUS', 'NEEDS-ACTION');
            }
        }

        if ($this->tags) {
            $vTodo->setAttribute('CATEGORIES', implode(', ', $this->tags));
        }

        /* Get the task's history. */
        $created = $modified = null;
        try {
            $log = $GLOBALS['injector']->getInstance('Horde_History')->getHistory('nag:' . $this->tasklist . ':' . $this->uid);
            foreach ($log as $entry) {
                switch ($entry['action']) {
                case 'add':
                    $created = $entry['ts'];
                    break;

                case 'modify':
                    $modified = $entry['ts'];
                    break;
                }
            }
        } catch (Exception $e) {}
        if (!empty($created)) {
            $vTodo->setAttribute($v1 ? 'DCREATED' : 'CREATED', $created);
            if (empty($modified)) {
                $modified = $created;
            }
        }
        if (!empty($modified)) {
            $vTodo->setAttribute('LAST-MODIFIED', $modified);
        }

        return $vTodo;
    }

    /**
     * Create an AS message from this task
     *
     * @param array $options  Options:
     *   - protocolversion: (float)  The EAS version to support
     *                      DEFAULT: 2.5
     *   - bodyprefs: (array)  A BODYPREFERENCE array.
     *                DEFAULT: none (No body prefs enforced).
     *   - truncation: (integer)  Truncate event body to this length
     *                 DEFAULT: none (No truncation).
     *
     * @return Horde_ActiveSync_Message_Task
     */
    public function toASTask(array $options = array())
    {
        $message = new Horde_ActiveSync_Message_Task(array(
            'protocolversion' => $options['protocolversion'])
        );

        /* Notes and Title */
        if ($options['protocolversion'] >= Horde_ActiveSync::VERSION_TWELVE) {
            $bp = $options['bodyprefs'];
            $body = new Horde_ActiveSync_Message_AirSyncBaseBody();
            $body->type = Horde_ActiveSync::BODYPREF_TYPE_PLAIN;
            if (isset($bp[Horde_ActiveSync::BODYPREF_TYPE_PLAIN]['truncationsize'])) {
                if (Horde_String::length($this->desc) > $bp[Horde_ActiveSync::BODYPREF_TYPE_PLAIN]['truncationsize']) {
                    $body->data = Horde_String::substr($this->desc, 0, $bp[Horde_ActiveSync::BODYPREF_TYPE_PLAIN]['truncationsize']);
                    $body->truncated = 1;
                } else {
                    $body->data = $this->desc;
                }
                $body->estimateddatasize = Horde_String::length($this->desc);
            }
            $message->airsyncbasebody = $body;
        } else {
            $message->body = $this->desc;
        }
        $message->subject = $this->name;

        /* Completion */
        if ($this->completed) {
            $message->datecompleted = new Horde_Date($this->completed_date);
            $message->complete = Horde_ActiveSync_Message_Task::TASK_COMPLETE_TRUE;
        } else {
            $message->complete = Horde_ActiveSync_Message_Task::TASK_COMPLETE_FALSE;
        }

        /* Due Date */
        if (!empty($this->due)) {
            $message->utcduedate = new Horde_Date($this->due);
        }

        /* Start Date */
        if (!empty($this->start)) {
            $message->utcstartdate = new Horde_Date($this->start);
        }

        /* Priority */
        switch ($this->priority) {
        case 5:
            $priority = Horde_ActiveSync_Message_Task::IMPORTANCE_LOW;
            break;
        case 4:
        case 3:
        case 2:
            $priority = Horde_ActiveSync_Message_Task::IMPORTANCE_NORMAL;
            break;
        case 1:
            $priority = Horde_ActiveSync_Message_Task::IMPORTANCE_HIGH;
            break;
        default:
            $priority = Horde_ActiveSync_Message_Task::IMPORTANCE_NORMAL;
        }
        $message->setImportance($priority);

        /* Reminders */
            if ($this->due && $this->alarm) {
            $message->setReminder(new Horde_Date($this->due - $this->alarm));
        }

        /* Recurrence */
        if ($this->recurs()) {
            $message->setRecurrence($this->recurrence);
        }

        return $message;
    }

    /**
     * Creates a task from a Horde_Icalendar_Vtodo object.
     *
     * @param Horde_Icalendar_Vtodo $vTodo  The iCalendar data to update from.
     */
    public function fromiCalendar(Horde_Icalendar_Vtodo $vTodo)
    {
        try {
            $name = $vTodo->getAttribute('SUMMARY');
            if (!is_array($name)) { $this->name = $name; }
        } catch (Horde_Icalendar_Exception $e) {}

        try {
            $assignee = $vTodo->getAttribute('ORGANIZER');
            if (!is_array($assignee)) { $this->assignee = $assignee; }
        } catch (Horde_Icalendar_Exception $e) {}

        try {
            $uid = $vTodo->getAttribute('UID');
            if (!is_array($uid)) { $this->uid = $uid; }
        } catch (Horde_Icalendar_Exception $e) {}

        try {
            $relations = $vTodo->getAttribute('RELATED-TO');
            if (!is_array($relations)) {
                $relations = array($relations);
            }
            $params = $vTodo->getAttribute('RELATED-TO', true);
            foreach ($relations as $id => $relation) {
                if (empty($params[$id]['RELTYPE']) ||
                    Horde_String::upper($params[$id]['RELTYPE']) == 'PARENT') {

                    $parent = $this->_storage->getByUID($relation);
                    $this->parent_id = $parent->id;
                    break;
                }
            }
        } catch (Horde_Icalendar_Exception $e) {}

        try {
            $start = $vTodo->getAttribute('DTSTART');
            if (!is_array($start)) {
                // Date-Time field
                $this->start = $start;
            } else {
                // Date field
                $this->start = mktime(0, 0, 0, (int)$start['month'], (int)$start['mday'], (int)$start['year']);
            }
        } catch (Horde_Icalendar_Exception $e) {}

        try {
            $due = $vTodo->getAttribute('DUE');
            if (is_array($due)) {
                $this->due = mktime(0, 0, 0, (int)$due['month'], (int)$due['mday'], (int)$due['year']);
            } elseif (!empty($due)) {
                $this->due = $due;
            }
        } catch (Horde_Icalendar_Exception $e) {}

        // vCalendar 1.0 alarms
        try {
            $alarm = $vTodo->getAttribute('AALARM');
            if (!is_array($alarm) && !empty($alarm) && !empty($this->due)) {
                $this->alarm = intval(($this->due - $alarm) / 60);
                if ($this->alarm === 0) {
                    // We don't support alarms exactly at due date.
                    $this->alarm = 1;
                }
            }
        } catch (Horde_Icalendar_Exception $e) {}

        // @TODO: vCalendar 2.0 alarms

        try {
            $desc = $vTodo->getAttribute('DESCRIPTION');
            if (!is_array($desc)) { $this->desc = $desc; }
        } catch (Horde_Icalendar_Exception $e) {}

        try {
            $priority = $vTodo->getAttribute('PRIORITY');
            if (!is_array($priority)) { $this->priority = $priority; }
        } catch (Horde_Icalendar_Exception $e) {}

        try {
            $cat = $vTodo->getAttribute('CATEGORIES');
            if (!is_array($cat)) {
                $this->tags = $cat;
            }
        } catch (Horde_Icalendar_Exception $e) {}

        try {
            $status = $vTodo->getAttribute('STATUS');
            if (!is_array($status)) { $this->completed = !strcasecmp($status, 'COMPLETED'); }
        } catch (Horde_Icalendar_Exception $e) {}

        try {
            $class = $vTodo->getAttribute('CLASS');
            if (!is_array($class)) {
                $class = Horde_String::upper($class);
                $this->private = $class == 'PRIVATE' || $class == 'CONFIDENTIAL';
            }
        } catch (Horde_Icalendar_Exception $e) {}
    }

    /**
     * Create a nag Task object from an activesync message
     *
     * @param Horde_ActiveSync_Message_Task $message  The task object
     */
    public function fromASTask(Horde_ActiveSync_Message_Task $message)
    {
        /* Owner is always current user for ActiveSync */
        $this->owner = $GLOBALS['registry']->getAuth();

        /* Notes and Title */
        if ($message->getProtocolVersion() >= Horde_ActiveSync::VERSION_TWELVE) {
            $this->desc = $message->airsyncbasebody->data;
        } else {
            $this->desc = $message->body;
        }

        $this->name = $message->subject;

        /* Completion */
        if ($this->completed = $message->complete) {
            $dateCompleted = $message->datecompleted;
            $this->completed_date = empty($dateCompleted) ? null : $dateCompleted;
        }

        /* Due Date */
        if ($due = $message->utcduedate) {
            $this->due = $due->timestamp();
        }

        /* Start Date */
        if ($start = $message->utcstartdate) {
            $this->start = $start->timestamp();
        }

        /* Priority */
        switch ($message->getImportance()) {
        case Horde_ActiveSync_Message_Task::IMPORTANCE_LOW;
            $this->priority = 5;
            break;
        case Horde_ActiveSync_Message_Task::IMPORTANCE_NORMAL;
            $this->priority = 3;
            break;
        case Horde_ActiveSync_Message_Task::IMPORTANCE_HIGH;
            $this->priority = 1;
            break;
        default:
            $this->priority = 3;
        }

        if (($alarm = $message->getReminder()) && $this->due) {
            $this->alarm = $this->due - $alarm->timestamp();
        }

        if ($rrule = $message->getRecurrence()) {
            $this->recurrence = $rrule;
        }

        $this->tasklist = $GLOBALS['prefs']->getValue('default_tasklist');
    }

}
