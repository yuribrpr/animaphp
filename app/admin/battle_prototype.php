<?php
require __DIR__ . '/../_init.php';

$user = require_login();
$pdo = db();

$pageTitle = 'Arena de Batalha (Protótipo)';

// 1. Fetch User's Main Anima
$stmt = $pdo->prepare("
    SELECT ua.*, a.species, a.image_path, a.attribute, a.max_health, a.attack, a.defense, a.attack_speed
    FROM user_animas ua 
    JOIN animas a ON ua.anima_id = a.id 
    WHERE ua.user_id = ? AND ua.is_main = 1
    LIMIT 1
");
$stmt->execute([$user['id']]);
$playerAnima = $stmt->fetch();

if (!$playerAnima) {
    set_flash('error', 'Você precisa definir um Anima Principal para batalhar!');
    redirect('/app/animas/my_animas.php');
}

// 2. Fetch Random Enemy
$stmt = $pdo->query("
    SELECT * FROM enemies 
    ORDER BY RAND() 
    LIMIT 1
");
$enemyAnima = $stmt->fetch();

if (!$enemyAnima) {
    set_flash('error', 'Nenhum inimigo encontrado na arena!');
    redirect('/app/admin/dashboard.php');
}

// Ensure attack_speed has a sensible default (ms)
$pAtkSpd = max(500, (int)($playerAnima['attack_speed'] ?? 2000));
$eAtkSpd = max(500, (int)($enemyAnima['attack_speed'] ?? 2000));

$pLevel = max(1, (int)($playerAnima['level'] ?? 1));
$pExp   = (int)($playerAnima['current_exp'] ?? 0);
$pNextExp = max(1, (int)($playerAnima['next_level_exp'] ?? 1000));

$battleData = [
    'player' => [
        'name'     => $playerAnima['nickname'],
        'species'  => $playerAnima['species'],
        'level'    => $pLevel,
        'exp'      => $pExp,
        'nextExp'  => $pNextExp,
        'hp'       => (int)$playerAnima['max_health'],
        'max_hp'   => (int)$playerAnima['max_health'],
        'atk'      => (int)$playerAnima['attack'],
        'def'      => (int)$playerAnima['defense'],
        'atkSpd'   => $pAtkSpd,
        'image'    => $playerAnima['image_path'] ?? '/dist/img/default-150x150.png'
    ],
    'enemy' => [
        'name'     => $enemyAnima['species'],
        'hp'       => (int)$enemyAnima['max_health'],
        'max_hp'   => (int)$enemyAnima['max_health'],
        'atk'      => (int)$enemyAnima['attack'],
        'def'      => (int)$enemyAnima['defense'],
        'atkSpd'   => $eAtkSpd,
        'image'    => $enemyAnima['image_path'] ?? '/dist/img/default-150x150.png'
    ]
];

$battleDataJson = json_encode($battleData);

$extraCss = [
    '/plugins/sweetalert2-theme-bootstrap-4/bootstrap-4.min.css',
];
$extraJs = [
    '/plugins/sweetalert2/sweetalert2.min.js',
];

$inlineJs = <<<JS
    var battleData = {$battleDataJson};

    function BattleSystem(data) {
        this.player = JSON.parse(JSON.stringify(data.player));
        this.enemy  = JSON.parse(JSON.stringify(data.enemy));
        this.isOver = false;
        this.started = false;
        this.turnCount = 0;
        this.playerCooldown = false;
        this.enemyTimer = null;
        this.updateUI();
        this.tlog('[SYS] Arena carregada. Oponente: ' + this.enemy.name + ' (HP:' + this.enemy.max_hp + ' ATK:' + this.enemy.atk + ' DEF:' + this.enemy.def + ' SPD:' + this.enemy.atkSpd + 'ms)', 'sys');
        this.tlog('[SYS] Pressione ATACAR para iniciar o combate.', 'sys');
    }

    BattleSystem.prototype.updateUI = function() {
        var pHp = Math.max(0, Math.floor(this.player.hp));
        document.getElementById('p-hp-text').textContent = pHp + ' / ' + this.player.max_hp;
        var pPct = Math.max(0, (this.player.hp / this.player.max_hp) * 100);
        var pBar = document.getElementById('p-hp-bar');
        pBar.style.width = pPct + '%';
        pBar.className = 'progress-bar progress-bar-striped progress-bar-animated';
        if (pPct > 50) pBar.className += ' bg-success';
        else if (pPct > 25) pBar.className += ' bg-warning';
        else pBar.className += ' bg-danger';

        var eHp = Math.max(0, Math.floor(this.enemy.hp));
        document.getElementById('e-hp-text').textContent = eHp + ' / ' + this.enemy.max_hp;
        var ePct = Math.max(0, (this.enemy.hp / this.enemy.max_hp) * 100);
        var eBar = document.getElementById('e-hp-bar');
        eBar.style.width = ePct + '%';
        eBar.className = 'progress-bar progress-bar-striped progress-bar-animated';
        if (ePct > 50) eBar.className += ' bg-success';
        else if (ePct > 25) eBar.className += ' bg-warning';
        else eBar.className += ' bg-danger';
    };

    BattleSystem.prototype.tlog = function(msg, type) {
        var el = document.getElementById('battle-log');
        var line = document.createElement('div');
        var cls = 'term-sys';
        if (type === 'atk') cls = 'term-atk';
        else if (type === 'def') cls = 'term-def';
        else if (type === 'res') cls = 'term-res';
        line.className = cls;
        line.textContent = msg;
        el.appendChild(line);
        el.scrollTop = el.scrollHeight;
    };

    BattleSystem.prototype.calcDamage = function(atk, def) {
        var atkRoll = 0.95 + Math.random() * 0.10;
        var defRoll = 0.95 + Math.random() * 0.10;
        var atkF = Math.floor(atk * atkRoll);
        var defF = Math.floor(def * defRoll);
        var dmg = Math.max(1, atkF - defF);
        return { dmg: dmg, atkF: atkF, defF: defF, pctAtk: Math.round(atkRoll * 100), pctDef: Math.round(defRoll * 100) };
    };

    BattleSystem.prototype.showSlash = function(targetId) {
        var parent = document.getElementById(targetId).closest('.anima-panel');
        var slash = document.createElement('div');
        slash.className = 'slash-fx';
        slash.innerHTML = '<svg viewBox="0 0 80 80" width="80" height="80"><line x1="10" y1="10" x2="70" y2="70" stroke="#ff3333" stroke-width="4" stroke-linecap="round" class="sl"/><line x1="70" y1="10" x2="10" y2="70" stroke="#ff3333" stroke-width="4" stroke-linecap="round" class="sl sl2"/></svg>';
        parent.appendChild(slash);
        setTimeout(function() { slash.remove(); }, 550);
    };

    BattleSystem.prototype.shakeSprite = function(id) {
        var el = document.getElementById(id);
        el.classList.add('hit-shake');
        setTimeout(function() { el.classList.remove('hit-shake'); }, 400);
    };

    BattleSystem.prototype.flashWhite = function(id) {
        var el = document.getElementById(id);
        el.style.filter = 'brightness(2.5)';
        setTimeout(function() { el.style.filter = ''; }, 100);
    };

    BattleSystem.prototype.showDmg = function(targetId, dmg) {
        var parent = document.getElementById(targetId).closest('.anima-panel');
        var num = document.createElement('div');
        num.className = 'dmg-pop';
        num.textContent = '-' + dmg;
        parent.appendChild(num);
        setTimeout(function() { num.remove(); }, 800);
    };

    BattleSystem.prototype.startCooldown = function() {
        var self = this;
        this.playerCooldown = true;
        var btn = document.getElementById('btn-attack');
        var bar = document.getElementById('cooldown-bar');
        var spd = this.player.atkSpd;
        btn.disabled = true;
        btn.classList.add('disabled');
        bar.style.transition = 'none';
        bar.style.width = '0%';

        // Force reflow then animate
        void bar.offsetWidth;
        bar.style.transition = 'width ' + (spd / 1000) + 's linear';
        bar.style.width = '100%';

        setTimeout(function() {
            if (!self.isOver) {
                self.playerCooldown = false;
                btn.disabled = false;
                btn.classList.remove('disabled');
            }
        }, spd);
    };

    BattleSystem.prototype.startEnemyLoop = function() {
        var self = this;
        this.enemyTimer = setInterval(function() {
            if (self.isOver) { clearInterval(self.enemyTimer); return; }
            self.enemyAttack();
        }, this.enemy.atkSpd);
    };

    BattleSystem.prototype.attack = function() {
        if (this.isOver || this.playerCooldown) return;
        this.turnCount++;

        if (!this.started) {
            this.started = true;
            this.tlog('[SYS] Combate iniciado!', 'sys');
            this.startEnemyLoop();
        }

        var r = this.calcDamage(this.player.atk, this.enemy.def);
        this.enemy.hp -= r.dmg;

        this.tlog('[ATK] ' + this.player.name + ' causou ' + r.dmg + ' de dano (' + r.atkF + ' atk@' + r.pctAtk + '% - ' + r.defF + ' def@' + r.pctDef + '% de ' + this.enemy.name + ') | HP restante: ' + Math.max(0, Math.floor(this.enemy.hp)) + '/' + this.enemy.max_hp, 'atk');

        var self = this;
        this.showSlash('enemy-sprite');
        setTimeout(function() {
            self.flashWhite('enemy-sprite');
            self.shakeSprite('enemy-sprite');
            self.showDmg('enemy-sprite', r.dmg);
        }, 180);

        setTimeout(function() {
            self.updateUI();
            if (self.enemy.hp <= 0) { self.win(); return; }
        }, 350);

        this.startCooldown();
    };

    BattleSystem.prototype.enemyAttack = function() {
        if (this.isOver) return;

        var r = this.calcDamage(this.enemy.atk, this.player.def);
        this.player.hp -= r.dmg;

        this.tlog('[DEF] ' + this.enemy.name + ' causou ' + r.dmg + ' de dano (' + r.atkF + ' atk@' + r.pctAtk + '% - ' + r.defF + ' def@' + r.pctDef + '% de ' + this.player.name + ') | HP restante: ' + Math.max(0, Math.floor(this.player.hp)) + '/' + this.player.max_hp, 'def');

        var self = this;
        this.showSlash('player-sprite');
        setTimeout(function() {
            self.flashWhite('player-sprite');
            self.shakeSprite('player-sprite');
            self.showDmg('player-sprite', r.dmg);
        }, 180);

        setTimeout(function() {
            self.updateUI();
            if (self.player.hp <= 0) { self.lose(); return; }
        }, 350);
    };

    BattleSystem.prototype.endBattle = function() {
        this.isOver = true;
        if (this.enemyTimer) clearInterval(this.enemyTimer);
        var btn = document.getElementById('btn-attack');
        btn.disabled = true;
        btn.classList.add('disabled');
    };

    BattleSystem.prototype.win = function() {
        this.endBattle();
        this.tlog('[===] RESULTADO: VITORIA | Turnos: ' + this.turnCount + ' | Oponente eliminado: ' + this.enemy.name, 'res');
        Swal.fire({
            title: 'Vitoria!',
            html: '<p>Voce derrotou <b>' + this.enemy.name + '</b> em <b>' + this.turnCount + '</b> turnos!</p>',
            icon: 'success',
            confirmButtonText: '<i class="fas fa-forward mr-1"></i> Proxima Batalha',
            confirmButtonColor: '#28a745',
            allowOutsideClick: false
        }).then(function(x) { if (x.isConfirmed) location.reload(); });
    };

    BattleSystem.prototype.lose = function() {
        this.endBattle();
        this.tlog('[===] RESULTADO: DERROTA | Turnos: ' + this.turnCount + ' | Seu anima foi eliminado.', 'res');
        Swal.fire({
            title: 'Derrota',
            html: '<p>Seu Anima foi derrotado apos <b>' + this.turnCount + '</b> turnos.</p>',
            icon: 'error',
            confirmButtonText: '<i class="fas fa-redo mr-1"></i> Tentar Novamente',
            confirmButtonColor: '#dc3545',
            allowOutsideClick: false
        }).then(function(x) { if (x.isConfirmed) location.reload(); });
    };

    $(function() {
        var battle = new BattleSystem(battleData);
        $('#btn-attack').on('click', function() { battle.attack(); });
        $('#btn-flee').on('click', function() {
            Swal.fire({
                title: 'Fugir?', text: 'Tem certeza que deseja fugir?', icon: 'warning',
                showCancelButton: true, confirmButtonText: 'Sim, fugir!', cancelButtonText: 'Continuar', confirmButtonColor: '#6c757d'
            }).then(function(x) { if (x.isConfirmed) location.reload(); });
        });
    });
JS;

$renderContent = function() use ($playerAnima, $enemyAnima, $pAtkSpd, $eAtkSpd, $pLevel, $pExp, $pNextExp) {
    $playerImg = e($playerAnima['image_path'] ?? '/dist/img/default-150x150.png');
    $enemyImg  = e($enemyAnima['image_path'] ?? '/dist/img/default-150x150.png');
?>
    <style>
        /* Tech grid background */
        .battle-bg {
            background:
                linear-gradient(rgba(0,0,0,0.02) 1px, transparent 1px),
                linear-gradient(90deg, rgba(0,0,0,0.02) 1px, transparent 1px),
                linear-gradient(rgba(0,100,200,0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(0,100,200,0.03) 1px, transparent 1px);
            background-size: 20px 20px, 20px 20px, 100px 100px, 100px 100px;
            border-radius: 4px;
            padding: 25px 15px;
            position: relative;
        }
        .battle-bg::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: radial-gradient(ellipse at center, rgba(0,120,255,0.04) 0%, transparent 70%);
            pointer-events: none;
        }

        /* Sprites */
        .anima-panel { position: relative; overflow: hidden; }
        .anima-sprite {
            width: 110px; height: 110px;
            object-fit: contain;
            transition: filter 0.1s;
        }

        .hit-shake { animation: hitShake 0.4s cubic-bezier(.36,.07,.19,.97) both; }
        @keyframes hitShake {
            10%, 90% { transform: translateX(-4px); }
            20%, 80% { transform: translateX(6px); }
            30%, 50%, 70% { transform: translateX(-8px); }
            40%, 60% { transform: translateX(8px); }
        }
        .enemy-flip { transform: scaleX(-1); }
        .enemy-flip.hit-shake { animation: hitShakeFlip 0.4s cubic-bezier(.36,.07,.19,.97) both; }
        @keyframes hitShakeFlip {
            10%, 90% { transform: scaleX(-1) translateX(-4px); }
            20%, 80% { transform: scaleX(-1) translateX(6px); }
            30%, 50%, 70% { transform: scaleX(-1) translateX(-8px); }
            40%, 60% { transform: scaleX(-1) translateX(8px); }
        }

        .dmg-pop {
            position: absolute; top: 10px; left: 50%;
            transform: translateX(-50%);
            font-size: 1.5rem; font-weight: 900;
            color: #dc3545;
            text-shadow: 1px 1px 0 #000, -1px -1px 0 #000;
            animation: popUp 0.8s ease-out forwards;
            pointer-events: none; z-index: 10;
        }
        @keyframes popUp {
            0% { opacity: 1; transform: translateX(-50%) translateY(0) scale(1.3); }
            100% { opacity: 0; transform: translateX(-50%) translateY(-45px) scale(0.7); }
        }

        .slash-fx {
            position: absolute; top: 50%; left: 50%;
            transform: translate(-50%, -50%);
            pointer-events: none; z-index: 20;
            animation: slashIn 0.55s ease-out forwards;
        }
        @keyframes slashIn {
            0% { opacity: 0; transform: translate(-50%, -50%) scale(0.2) rotate(-20deg); }
            25% { opacity: 1; transform: translate(-50%, -50%) scale(1.1) rotate(0deg); }
            100% { opacity: 0; transform: translate(-50%, -50%) scale(1.4) rotate(8deg); }
        }
        .sl {
            stroke-dasharray: 90; stroke-dashoffset: 90;
            animation: drawSl 0.25s 0.05s ease-out forwards;
        }
        .sl2 { animation-delay: 0.13s; }
        @keyframes drawSl { to { stroke-dashoffset: 0; } }

        /* Cooldown bar */
        .cooldown-track {
            height: 4px; background: #dee2e6; border-radius: 2px;
            overflow: hidden; margin-top: 6px;
        }
        .cooldown-fill {
            height: 100%; width: 0%; background: #007bff;
        }

        /* Terminal log - AdminLTE card dark + monospace */
        .term-body {
            background: #1a1a2e;
            color: #00ff41;
            font-family: 'SFMono-Regular', 'Consolas', 'Courier New', monospace;
            font-size: 0.75rem;
            line-height: 1.6;
            padding: 10px 14px;
            max-height: 180px;
            overflow-y: auto;
            white-space: pre-wrap;
            word-break: break-all;
        }
        .term-body::-webkit-scrollbar { width: 5px; }
        .term-body::-webkit-scrollbar-track { background: #1a1a2e; }
        .term-body::-webkit-scrollbar-thumb { background: #333; border-radius: 3px; }
        .term-sys { color: #00ff41; }
        .term-atk { color: #33ccff; }
        .term-def { color: #ff6b6b; }
        .term-res { color: #ffd93d; font-weight: bold; }
        /* Stat popover */
        .stat-popover {
            position: absolute;
            bottom: calc(100% + 8px);
            left: 50%;
            transform: translateX(-50%) scale(0.95);
            background: #343a40;
            color: #fff;
            padding: 10px 14px;
            border-radius: 6px;
            font-size: 0.78rem;
            white-space: nowrap;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.2s, transform 0.2s;
            z-index: 30;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        }
        .stat-popover::after {
            content: '';
            position: absolute;
            top: 100%; left: 50%;
            transform: translateX(-50%);
            border: 6px solid transparent;
            border-top-color: #343a40;
        }
        .anima-panel:hover .stat-popover {
            opacity: 1;
            transform: translateX(-50%) scale(1);
            pointer-events: auto;
        }
        .stat-row { display: flex; align-items: center; gap: 6px; padding: 2px 0; }
        .stat-label { color: #adb5bd; min-width: 35px; }
        .stat-val { font-weight: 600; }

        /* EXP bar */
        .exp-track { height: 6px; border-radius: 3px; }
    </style>

    <div class="row">
        <div class="col-12">
            <!-- Main Battle Card -->
            <div class="card card-outline card-dark">
                <div class="card-header">
                    <h3 class="card-title">Arena de Batalha</h3>
                    <div class="card-tools">
                        <span class="badge badge-dark">Protótipo</span>
                    </div>
                </div>

                <div class="card-body battle-bg">
                    <div class="row align-items-center">

                        <!-- Player Panel -->
                        <div class="col-md-5">
                            <div class="card card-outline card-primary mb-0 shadow-sm">
                                <div class="card-header py-2">
                                    <h3 class="card-title">
                                        <?= e($playerAnima['nickname']) ?>
                                        <small class="text-muted ml-1">(Lv <?= $pLevel ?>)</small>
                                    </h3>
                                    <div class="card-tools">
                                        <span class="badge badge-info"><?= e($playerAnima['species']) ?></span>
                                    </div>
                                </div>
                                <div class="card-body text-center py-3 anima-panel">
                                    <div class="stat-popover">
                                        <div class="stat-row"><span class="stat-label">ATK</span> <span class="stat-val text-info"><?= e($playerAnima['attack']) ?></span></div>
                                        <div class="stat-row"><span class="stat-label">DEF</span> <span class="stat-val text-primary"><?= e($playerAnima['defense']) ?></span></div>
                                        <div class="stat-row"><span class="stat-label">SPD</span> <span class="stat-val text-secondary"><?= $pAtkSpd ?>ms</span></div>
                                    </div>
                                    <img id="player-sprite" src="<?= $playerImg ?>" class="anima-sprite mb-2" alt="Anima">
                                    <div class="mt-2">
                                        <small class="text-muted d-flex justify-content-between">
                                            <span>HP</span>
                                            <span id="p-hp-text">-</span>
                                        </small>
                                        <div class="progress" style="height: 16px;">
                                            <div id="p-hp-bar" class="progress-bar progress-bar-striped progress-bar-animated bg-success" style="width: 100%"></div>
                                        </div>
                                        <small class="text-muted d-flex justify-content-between mt-2">
                                            <span>EXP</span>
                                            <span><?= $pExp ?> / <?= $pNextExp ?></span>
                                        </small>
                                        <div class="progress exp-track">
                                            <div class="progress-bar bg-info" style="width: <?= round(($pExp / $pNextExp) * 100) ?>%"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- VS -->
                        <div class="col-md-2 text-center py-3">
                            <h2 class="text-muted font-weight-bold" style="font-size: 2.2rem; opacity: 0.6;">VS</h2>
                        </div>

                        <!-- Enemy Panel -->
                        <div class="col-md-5">
                            <div class="card card-outline card-danger mb-0 shadow-sm">
                                <div class="card-header py-2">
                                    <h3 class="card-title">
                                        <span id="e-name"><?= e($enemyAnima['species']) ?></span>
                                    </h3>
                                    <div class="card-tools">
                                        <span class="badge badge-danger">Inimigo</span>
                                    </div>
                                </div>
                                <div class="card-body text-center py-3 anima-panel">
                                    <div class="stat-popover">
                                        <div class="stat-row"><span class="stat-label">ATK</span> <span class="stat-val text-info"><?= e($enemyAnima['attack']) ?></span></div>
                                        <div class="stat-row"><span class="stat-label">DEF</span> <span class="stat-val text-primary"><?= e($enemyAnima['defense']) ?></span></div>
                                        <div class="stat-row"><span class="stat-label">SPD</span> <span class="stat-val text-secondary"><?= $eAtkSpd ?>ms</span></div>
                                    </div>
                                    <img id="enemy-sprite" src="<?= $enemyImg ?>" class="anima-sprite enemy-flip mb-2" alt="Inimigo">
                                    <div class="mt-2">
                                        <small class="text-muted d-flex justify-content-between">
                                            <span>HP</span>
                                            <span id="e-hp-text">-</span>
                                        </small>
                                        <div class="progress" style="height: 16px;">
                                            <div id="e-hp-bar" class="progress-bar progress-bar-striped progress-bar-animated bg-success" style="width: 100%"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- Actions -->
                <div class="card-footer">
                    <div class="row">
                        <div class="col-md-6 mx-auto">
                            <div class="btn-group btn-group-lg btn-block" role="group">
                                <button id="btn-attack" class="btn btn-danger">
                                    <i class="fas fa-fist-raised mr-1"></i> Atacar
                                </button>
                                <button id="btn-flee" class="btn btn-outline-secondary">
                                    <i class="fas fa-running mr-1"></i> Fugir
                                </button>
                            </div>
                            <div class="cooldown-track">
                                <div id="cooldown-bar" class="cooldown-fill"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Terminal Log -->
            <div class="card card-dark mb-0">
                <div class="card-header py-2">
                    <h3 class="card-title"><i class="fas fa-terminal mr-2"></i>Battle Log</h3>
                    <div class="card-tools">
                        <button type="button" class="btn btn-tool" data-card-widget="collapse">
                            <i class="fas fa-minus"></i>
                        </button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="term-body" id="battle-log"></div>
                </div>
            </div>

        </div>
    </div>
<?php
};

require __DIR__ . '/../_layout.php';
