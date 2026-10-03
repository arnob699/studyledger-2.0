    </div>
  </div>
</div>

<div class="modal-backdrop" id="manual-log-modal">
  <div class="modal">
    <div class="modal-header">
      <h2>Log a past session</h2>
      <button type="button" class="btn-close" onclick="closeModal('manual-log-modal')" aria-label="Close">&times;</button>
    </div>
    <?php if (empty($logSubjects)): ?>
      <div class="empty">
        <h3>No subjects yet</h3>
        <p>Add a subject first, then you can log time against it.</p>
        <a href="subjects.php" class="btn btn-primary mt-4" style="display:inline-flex;">Add a subject</a>
      </div>
    <?php else: ?>
      <form id="manual-log-form">
        <div class="field">
          <label for="log_subject">Subject</label>
          <select id="log_subject" required>
            <?php foreach ($logSubjects as $s): ?>
              <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="log_material">Material <span class="muted" style="font-weight:400;">(optional)</span></label>
          <select id="log_material">
            <option value="">No specific material</option>
          </select>
        </div>
        <div class="grid grid-2">
          <div class="field">
            <label for="log_date">Date</label>
            <input type="date" id="log_date" value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>" required>
          </div>
          <div class="field">
            <label for="log_status">Status</label>
            <select id="log_status">
              <option value="completed">Completed</option>
              <option value="abandoned">Abandoned</option>
            </select>
          </div>
        </div>
        <div class="grid grid-2">
          <div class="field">
            <label for="log_study">Study minutes</label>
            <input type="number" id="log_study" min="0" max="600" value="<?= (int)$appSettings['default_study_minutes'] ?>">
          </div>
          <div class="field">
            <label for="log_revision">Revision minutes</label>
            <input type="number" id="log_revision" min="0" max="300" value="<?= (int)$appSettings['default_revision_minutes'] ?>">
          </div>
        </div>
        <span class="help">Backfilling dates out of order may throw off your streak count.</span>
        <div class="form-actions">
          <button type="button" class="btn btn-ghost" onclick="closeModal('manual-log-modal')">Cancel</button>
          <button type="submit" class="btn btn-primary">Save session</button>
        </div>
      </form>
    <?php endif; ?>
  </div>
</div>

<div id="toast"></div>

<script src="assets/js/app.js"></script>
<script src="assets/js/manual-log.js"></script>
<?php if (!empty($extraScripts)): foreach ($extraScripts as $src): ?>
<script src="<?= htmlspecialchars($src) ?>"></script>
<?php endforeach; endif; ?>
</body>
</html>
