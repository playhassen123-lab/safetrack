<?php
require_once '../../config/database.php';
include '../../includes/header.php';
include '../../includes/navbar.php';
include '../../includes/sidebar.php';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $template_name = trim($_POST['template_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $questions = $_POST['questions'] ?? [];
    
    if (empty($template_name)) {
        $error = 'Template name is required.';
    } else {
        try {
            $pdo->beginTransaction();
            
            // Insert template
            $stmt = $pdo->prepare("INSERT INTO checklist_templates (template_name, description, created_at) VALUES (?, ?, NOW())");
            $stmt->execute([$template_name, $description]);
            $template_id = $pdo->lastInsertId();
            
            // Insert questions if provided
            if (!empty($questions)) {
                $q_stmt = $pdo->prepare("INSERT INTO checklist_questions (template_id, question, question_order) VALUES (?, ?, ?)");
                foreach ($questions as $order => $question) {
                    $question = trim($question);
                    if (!empty($question)) {
                        $q_stmt->execute([$template_id, $question, $order + 1]);
                    }
                }
            }
            
            $pdo->commit();
            header("Location: view.php?id=$template_id&created=1");
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Error creating template: ' . $e->getMessage();
        }
    }
}
?>

<div class="content-wrapper">
<section class="content-header">
<div class="container-fluid">
<div class="row mb-2">
<div class="col-sm-6"><h1>Create Checklist Template</h1></div>
<div class="col-sm-6 text-end"><a href="index.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back</a></div>
</div>
</div>
</section>
<section class="content">
<div class="container-fluid">

<?php if ($error): ?>
<div class="alert alert-danger alert-dismissible fade show">
<i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<form method="POST">

<div class="card card-primary">
<div class="card-header"><h3 class="card-title">Template Information</h3></div>
<div class="card-body">
<div class="form-group mb-3">
<label>Template Name *</label>
<input type="text" name="template_name" class="form-control" placeholder="e.g., Fire Safety Inspection" required value="<?= isset($template_name) ? htmlspecialchars($template_name) : '' ?>">
</div>
<div class="form-group mb-3">
<label>Description</label>
<textarea name="description" rows="3" class="form-control" placeholder="Brief description of this checklist template"><?= isset($description) ? htmlspecialchars($description) : '' ?></textarea>
</div>
</div>
</div>

<div class="card card-primary mt-3">
<div class="card-header">
<h3 class="card-title">Checklist Questions</h3>
<div class="card-tools">
<button type="button" class="btn btn-sm btn-info" id="addQuestion"><i class="fas fa-plus"></i> Add Question</button>
</div>
</div>
<div class="card-body">
<div id="questionsContainer">
<?php
// If form submitted with questions, repopulate them; otherwise show one empty row
$oldQuestions = $_POST['questions'] ?? [''];
$qc = 0;
foreach ($oldQuestions as $q) {
    $qc++;
    $qText = htmlspecialchars($q);
    echo "<div class=\"question-row mb-2\" data-index=\"" . ($qc-1) . "\">\n";
    echo "  <div class=\"input-group\">\n";
    echo "    <span class=\"input-group-text\">Q{$qc}</span>\n";
    echo "    <input type=\"text\" name=\"questions[]\" class=\"form-control\" placeholder=\"Enter question\" value=\"{$qText}\">\n";
    echo "    <button type=\"button\" class=\"btn btn-outline-danger removeQuestion\" style=\"display:none;\"><i class=\"fas fa-trash\"></i></button>\n";
    echo "  </div>\n";
    echo "</div>\n";
}
?>
</div>
</div>
<div class="card-footer">
<button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Create Template</button>
<a href="index.php" class="btn btn-secondary">Cancel</a>
</div>
</div>

</form>

</div>
</section>
</div>

<script>
let questionCount = document.querySelectorAll('.question-row').length || 1;

document.getElementById('addQuestion').addEventListener('click', function() {
    const container = document.getElementById('questionsContainer');
    const newRow = document.createElement('div');
    newRow.className = 'question-row mb-2';
    newRow.innerHTML = `
        <div class="input-group">
            <span class="input-group-text">Q${++questionCount}</span>
            <input type="text" name="questions[]" class="form-control" placeholder="Enter question">
            <button type="button" class="btn btn-outline-danger removeQuestion"><i class="fas fa-trash"></i></button>
        </div>
    `;
    container.appendChild(newRow);
    updateRemoveButtons();
});

document.addEventListener('click', function(e) {
    if (e.target.closest('.removeQuestion')) {
        e.preventDefault();
        e.target.closest('.question-row').remove();
        updateQuestionNumbers();
        updateRemoveButtons();
    }
});

function updateQuestionNumbers() {
    document.querySelectorAll('.question-row').forEach((row, idx) => {
        row.querySelector('.input-group-text').textContent = 'Q' + (idx + 1);
    });
    questionCount = document.querySelectorAll('.question-row').length;
}

function updateRemoveButtons() {
    const rows = document.querySelectorAll('.question-row');
    rows.forEach((row, idx) => {
        const btn = row.querySelector('.removeQuestion');
        if (btn) btn.style.display = rows.length > 1 ? 'block' : 'none';
    });
}

updateRemoveButtons();
</script>

<?php include '../../includes/footer.php'; ?>
