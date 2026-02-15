<?php
require __DIR__ . '/../_init.php';

$user = require_login();
$pdo = db();

function battle_json_response(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function battle_enemy_power_score(array $enemy): int
{
    $attack = max(0, (int)($enemy['attack'] ?? 0));
    $defense = max(0, (int)($enemy['defense'] ?? 0));
    $attackSpeed = max(500, (int)($enemy['attack_speed'] ?? 2000));
    $critChance = max(0.0, (float)($enemy['crit_chance'] ?? 0.0));

    $speedPower = max(1, (int)round(250000 / $attackSpeed));
    $critPower = (int)round($critChance * 20);

    return max(1, $attack + $defense + $speedPower + $critPower);
}

function battle_enemy_base_reward(array $enemy, string $column): int
{
    $storedReward = (int)($enemy[$column] ?? 0);
    if ($storedReward > 0) {
        return $storedReward;
    }

    $score = battle_enemy_power_score($enemy);
    return max(1, (int)round($score * 0.10));
}

function battle_reward_with_variation(int $baseReward): int
{
    $factor = random_int(90, 110) / 100;
    return max(1, (int)round($baseReward * $factor));
}

if (!isset($_SESSION['_battle_claims']) || !is_array($_SESSION['_battle_claims'])) {
    $_SESSION['_battle_claims'] = [];
}

foreach ($_SESSION['_battle_claims'] as $token => $claimData) {
    $createdAt = (int)($claimData['created_at'] ?? 0);
    if ($createdAt <= 0 || (time() - $createdAt) > 3600) {
        unset($_SESSION['_battle_claims'][$token]);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['action'] ?? '') === 'claim_reward') {
    if (!csrf_validate($_POST['_csrf'] ?? null)) {
        battle_json_response(['ok' => false, 'message' => 'Sessao expirada. Atualize a pagina e tente novamente.'], 419);
    }

    $battleToken = trim((string)($_POST['battle_token'] ?? ''));
    if ($battleToken === '' || !isset($_SESSION['_battle_claims'][$battleToken])) {
        battle_json_response(['ok' => false, 'message' => 'Token de batalha invalido.'], 400);
    }

    $claimData = $_SESSION['_battle_claims'][$battleToken];
    if (!is_array($claimData)) {
        unset($_SESSION['_battle_claims'][$battleToken]);
        battle_json_response(['ok' => false, 'message' => 'Dados de batalha invalidos.'], 400);
    }

    $claimUserId = (int)($claimData['user_id'] ?? 0);
    $claimAnimaId = (int)($claimData['user_anima_id'] ?? 0);
    $claimEnemyId = (int)($claimData['enemy_id'] ?? 0);
    $createdAt = (int)($claimData['created_at'] ?? 0);

    if ($claimUserId !== (int)$user['id']) {
        unset($_SESSION['_battle_claims'][$battleToken]);
        battle_json_response(['ok' => false, 'message' => 'Batalha nao pertence ao usuario atual.'], 403);
    }

    if ($createdAt <= 0 || (time() - $createdAt) > 1800) {
        unset($_SESSION['_battle_claims'][$battleToken]);
        battle_json_response(['ok' => false, 'message' => 'Batalha expirada. Inicie uma nova luta.'], 410);
    }

    $stmtEnemyReward = $pdo->prepare('SELECT id, attack, defense, attack_speed, crit_chance, reward_exp, reward_bits FROM enemies WHERE id = ? LIMIT 1');
    $stmtEnemyReward->execute([$claimEnemyId]);
    $enemyRewardData = $stmtEnemyReward->fetch();

    if (!$enemyRewardData) {
        unset($_SESSION['_battle_claims'][$battleToken]);
        battle_json_response(['ok' => false, 'message' => 'Inimigo da batalha nao encontrado.'], 404);
    }

    $baseExpReward = battle_enemy_base_reward($enemyRewardData, 'reward_exp');
    $baseBitsReward = battle_enemy_base_reward($enemyRewardData, 'reward_bits');
    $expGained = battle_reward_with_variation($baseExpReward);
    $bitsGained = battle_reward_with_variation($baseBitsReward);

    $newLevel = 1;
    $levelsGained = 0;
    $newCurrentExp = 0;
    $newNextExp = 1000;

    try {
        $pdo->beginTransaction();

        $stmtPlayer = $pdo->prepare('SELECT id, level, current_exp, next_level_exp FROM user_animas WHERE id = ? AND user_id = ? LIMIT 1 FOR UPDATE');
        $stmtPlayer->execute([$claimAnimaId, $claimUserId]);
        $playerRow = $stmtPlayer->fetch();

        if (!$playerRow) {
            throw new RuntimeException('Anima da batalha nao encontrado para recompensa.');
        }

        $currentLevel = max(1, (int)($playerRow['level'] ?? 1));
        $currentExp = max(0, (int)($playerRow['current_exp'] ?? 0)) + $expGained;
        $nextExp = max(1, (int)($playerRow['next_level_exp'] ?? 1000));

        while ($currentExp >= $nextExp) {
            $currentExp -= $nextExp;
            $currentLevel++;
            $levelsGained++;
            $nextExp = max(1, (int)ceil($nextExp * 1.25));
        }

        $stmtUpdateAnima = $pdo->prepare('UPDATE user_animas SET level = ?, current_exp = ?, next_level_exp = ? WHERE id = ?');
        $stmtUpdateAnima->execute([$currentLevel, $currentExp, $nextExp, $claimAnimaId]);

        $stmtUpdateUserBits = $pdo->prepare('UPDATE users SET bits = bits + ? WHERE id = ?');
        $stmtUpdateUserBits->execute([$bitsGained, $claimUserId]);

        $stmtUserBits = $pdo->prepare('SELECT bits FROM users WHERE id = ? LIMIT 1');
        $stmtUserBits->execute([$claimUserId]);
        $totalBits = (int)($stmtUserBits->fetchColumn() ?: 0);

        $pdo->commit();
        unset($_SESSION['_battle_claims'][$battleToken]);

        $newLevel = $currentLevel;
        $newCurrentExp = $currentExp;
        $newNextExp = $nextExp;

        battle_json_response([
            'ok' => true,
            'exp_gained' => $expGained,
            'bits_gained' => $bitsGained,
            'levels_gained' => $levelsGained,
            'new_level' => $newLevel,
            'current_exp' => $newCurrentExp,
            'next_level_exp' => $newNextExp,
            'total_bits' => $totalBits,
        ]);
    } catch (Throwable $t) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        battle_json_response(['ok' => false, 'message' => 'Falha ao aplicar recompensas.'], 500);
    }
}

$mapId = (int)($_GET['map_id'] ?? 0);
$debugMode = isset($_GET['debug']);

if ($mapId <= 0 && $debugMode) {
    $stmtRandomMap = $pdo->query(
        "SELECT m.id, m.name, m.background_image_path
         FROM maps m
         WHERE EXISTS (
             SELECT 1
             FROM map_enemies me
             INNER JOIN enemies e ON e.id = me.enemy_id
             WHERE me.map_id = m.id
         )
         ORDER BY RAND()
         LIMIT 1"
    );
    $randomMap = $stmtRandomMap->fetch();
    $mapId = (int)($randomMap['id'] ?? 0);
}

if ($mapId <= 0) {
    set_flash('error', 'Selecione um mapa válido para iniciar a batalha.');
    redirect('/app/admin/maps.php');
}

$stmtMap = $pdo->prepare('SELECT id, name, background_image_path FROM maps WHERE id = ? LIMIT 1');
$stmtMap->execute([$mapId]);
$selectedMap = $stmtMap->fetch();

if (!$selectedMap) {
    set_flash('error', 'Mapa não encontrado.');
    redirect('/app/admin/maps.php');
}

$mapName = (string)($selectedMap['name'] ?? 'Mapa');
$mapBackgroundPath = (string)($selectedMap['background_image_path'] ?? '');
if ($mapBackgroundPath === '') {
    $mapBackgroundPath = '/dist/img/photo1.png';
}
$safeMapBackgroundPath = str_replace("'", '%27', $mapBackgroundPath);
$battleBgStyle = "--map-bg-url: url('{$safeMapBackgroundPath}');";

$pageTitle = 'Arena de Batalha | ' . $mapName;

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

// 2. Fetch Random Enemy for current map
$stmt = $pdo->prepare(
    "SELECT e.*
     FROM map_enemies me
     INNER JOIN enemies e ON e.id = me.enemy_id
     WHERE me.map_id = ?
     ORDER BY RAND()
     LIMIT 1"
);
$stmt->execute([$mapId]);
$enemyAnima = $stmt->fetch();

if (!$enemyAnima) {
    set_flash('error', 'Nenhum inimigo vinculado a este mapa.');
    redirect('/app/admin/maps.php');
}

// Ensure attack_speed has a sensible default (ms)
$pAtkSpd = max(500, (int)($playerAnima['attack_speed'] ?? 2000));
$eAtkSpd = max(500, (int)($enemyAnima['attack_speed'] ?? 2000));

$pLevel = max(1, (int)($playerAnima['level'] ?? 1));
$pExp   = (int)($playerAnima['current_exp'] ?? 0);
$pNextExp = max(1, (int)($playerAnima['next_level_exp'] ?? 1000));
$baseRewardExp = battle_enemy_base_reward($enemyAnima, 'reward_exp');
$baseRewardBits = battle_enemy_base_reward($enemyAnima, 'reward_bits');
$battleToken = bin2hex(random_bytes(24));
$_SESSION['_battle_claims'][$battleToken] = [
    'user_id' => (int)$user['id'],
    'user_anima_id' => (int)$playerAnima['id'],
    'enemy_id' => (int)$enemyAnima['id'],
    'map_id' => $mapId,
    'created_at' => time(),
];

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
        'image'    => $enemyAnima['image_path'] ?? '/dist/img/default-150x150.png',
    ],
    'reward' => [
        'expBase' => $baseRewardExp,
        'bitsBase' => $baseRewardBits,
        'csrfToken' => csrf_token(),
        'claimToken' => $battleToken,
        'claimUrl' => '/app/admin/battle_prototype.php',
    ],
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
        this.reward = JSON.parse(JSON.stringify(data.reward || {}));
        this.isOver = false;
        this.started = false;
        this.turnCount = 0;
        this.playerCooldown = false;
        this.enemyTimer = null;
        this.updateUI();
        this.tlog('[SYS] Arena carregada. Oponente: ' + this.enemy.name + ' (HP:' + this.enemy.max_hp + ' ATK:' + this.enemy.atk + ' DEF:' + this.enemy.def + ' SPD:' + this.enemy.atkSpd + 'ms)', 'sys');
        if (this.reward.expBase && this.reward.bitsBase) {
            this.tlog('[SYS] Recompensa base: ' + this.reward.expBase + ' EXP / ' + this.reward.bitsBase + ' Bits (variacao 90-110%).', 'sys');
        }
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

    BattleSystem.prototype.claimVictoryRewards = function() {
        var self = this;
        if (!this.reward || !this.reward.claimToken || !this.reward.csrfToken || !this.reward.claimUrl) {
            return Promise.resolve({ ok: false, message: 'Sem token de recompensa para esta batalha.' });
        }

        var payload = new URLSearchParams();
        payload.append('action', 'claim_reward');
        payload.append('_csrf', this.reward.csrfToken);
        payload.append('battle_token', this.reward.claimToken);

        return fetch(this.reward.claimUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: payload.toString()
        })
            .then(function(resp) { return resp.json(); })
            .catch(function() {
                return { ok: false, message: 'Nao foi possivel processar as recompensas.' };
            })
            .then(function(result) {
                if (result && result.ok) {
                    self.reward.claimToken = '';
                }
                return result;
            });
    };

    BattleSystem.prototype.win = function() {
        var self = this;
        this.endBattle();
        this.tlog('[===] RESULTADO: VITORIA | Turnos: ' + this.turnCount + ' | Oponente eliminado: ' + this.enemy.name, 'res');

        this.claimVictoryRewards().then(function(rewardResult) {
            var rewardHtml = '';

            if (rewardResult && rewardResult.ok) {
                self.tlog('[SYS] Recompensas recebidas: +' + rewardResult.exp_gained + ' EXP / +' + rewardResult.bits_gained + ' Bits.', 'res');
                rewardHtml += '<p class="mb-1">Recompensas: <b>+' + rewardResult.exp_gained + ' EXP</b> e <b>+' + rewardResult.bits_gained + ' Bits</b>.</p>';
                rewardHtml += '<p class="mb-1">Bits totais do domador: <b>' + rewardResult.total_bits + '</b>.</p>';
                if (rewardResult.levels_gained > 0) {
                    self.tlog('[SYS] Level up! Novo nivel do seu Anima: ' + rewardResult.new_level + '.', 'res');
                    rewardHtml += '<p class="mb-0">Seu Anima subiu para o nivel <b>' + rewardResult.new_level + '</b>.</p>';
                }
            } else {
                var errMsg = (rewardResult && rewardResult.message) ? rewardResult.message : 'Recompensa nao aplicada.';
                self.tlog('[SYS] ' + errMsg, 'sys');
                rewardHtml += '<p class="mb-0 text-warning">' + errMsg + '</p>';
            }

            Swal.fire({
                title: 'Vitoria!',
                html: '<p>Voce derrotou <b>' + self.enemy.name + '</b> em <b>' + self.turnCount + '</b> turnos!</p>' + rewardHtml,
                icon: 'success',
                confirmButtonText: '<i class="fas fa-forward mr-1"></i> Proxima Batalha',
                confirmButtonColor: '#28a745',
                allowOutsideClick: false
            }).then(function(x) { if (x.isConfirmed) location.reload(); });
        });
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

$renderContent = function() use ($playerAnima, $enemyAnima, $pAtkSpd, $eAtkSpd, $pLevel, $pExp, $pNextExp, $mapName, $debugMode, $battleBgStyle) {
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
            padding: 20px 14px;
            position: relative;
            overflow: hidden;
            width: 100%;
            aspect-ratio: 16 / 9;
        }
        .battle-bg::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: radial-gradient(ellipse at center, rgba(0,120,255,0.04) 0%, transparent 70%);
            pointer-events: none;
            z-index: 1;
        }
        .battle-bg::after {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background-image: var(--map-bg-url);
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            pointer-events: none;
            z-index: 0;
        }
        .battle-bg > .row {
            position: relative;
            z-index: 2;
            height: 100%;
        }
        .battle-stage { height: 100%; }
        .battle-side {
            position: relative;
            height: 100%;
        }
        .battle-hud {
            background: rgba(255, 255, 255, 0.86);
            border: 1px solid rgba(0, 0, 0, 0.1);
            border-radius: 0.35rem;
            padding: 0.55rem 0.7rem;
            position: absolute;
            top: 0;
            left: 18px;
            right: 18px;
        }
        .battle-hud small {
            color: #495057 !important;
        }
        .battle-name {
            font-weight: 600;
            color: #1f2d3d;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        .battle-progress {
            height: 14px;
        }
        .battle-log-col { display: flex; }
        .battle-log-col .card { width: 100%; }
        .battle-log-col .term-body {
            height: clamp(260px, 46vh, 520px);
            max-height: 520px;
            overflow-y: auto;
        }
        @media (max-width: 991.98px) {
            .battle-bg {
                padding: 12px 10px;
            }
            .battle-hud {
                left: 10px;
                right: 10px;
            }
            .battle-log-col .term-body {
                height: 220px;
                max-height: 220px;
            }
        }

        /* Sprites */
        .anima-panel {
            position: absolute;
            left: 50%;
            bottom: 30%;
            transform: translateX(-50%);
            display: flex;
            justify-content: center;
            align-items: center;
            overflow: visible;
        }
        .anima-sprite {
            width: 138px; height: 138px;
            object-fit: contain;
            transition: filter 0.1s, transform 0.2s ease;
            cursor: help;
        }
        .anima-sprite:hover {
            transform: translateY(-2px) scale(1.03);
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

        /* Battle log - AdminLTE style */
        .term-body {
            background: #ffffff;
            color: #343a40;
            font-size: 0.875rem;
            line-height: 1.45;
            padding: 0.75rem 0.9rem;
            overflow-y: auto;
            white-space: pre-wrap;
            word-break: break-word;
        }
        .term-body > div {
            padding: 0.18rem 0;
            border-bottom: 1px dashed #e9ecef;
        }
        .term-body > div:last-child {
            border-bottom: 0;
        }
        .term-sys { color: #6c757d; }
        .term-atk { color: #007bff; }
        .term-def { color: #dc3545; }
        .term-res { color: #28a745; font-weight: 600; }
        /* Stat popover */
        .stat-popover {
            position: absolute;
            bottom: calc(100% + 10px);
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
        .anima-sprite:hover + .stat-popover,
        .stat-popover:hover {
            opacity: 1;
            transform: translateX(-50%) scale(1);
            pointer-events: auto;
        }
        .stat-row { display: flex; align-items: center; gap: 6px; padding: 2px 0; }
        .stat-label { color: #adb5bd; min-width: 35px; }
        .stat-val { font-weight: 600; }

        /* EXP bar */
        .exp-track { height: 6px; border-radius: 3px; }
        @media (max-width: 575.98px) {
            .anima-panel {
                bottom: 24%;
            }
            .anima-sprite {
                width: 102px;
                height: 102px;
            }
            .battle-hud {
                padding: 0.5rem 0.55rem;
            }
        }
    </style>

    <div class="row">
        <div class="col-12 col-lg-8">
            <!-- Main Battle Card -->
            <div class="card card-outline card-dark">
                <div class="card-header">
                    <h3 class="card-title">Arena de Batalha</h3>
                    <div class="card-tools">
                        <span class="badge badge-info mr-1"><?= e($mapName) ?></span>
                        <?php if ($debugMode): ?>
                            <span class="badge badge-secondary">Debug</span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card-body battle-bg" style="<?= e($battleBgStyle) ?>">
                    <div class="row battle-stage">
                        <div class="col-6 battle-side">
                            <div class="battle-hud mb-2">
                                <div class="battle-name mb-1">
                                    <span>
                                        <?= e($playerAnima['nickname']) ?>
                                        <small class="text-muted ml-1">(Lv <?= $pLevel ?>)</small>
                                    </span>
                                    <span class="badge badge-info"><?= e($playerAnima['species']) ?></span>
                                </div>
                                <small class="d-flex justify-content-between">
                                    <span>HP</span>
                                    <span id="p-hp-text">-</span>
                                </small>
                                <div class="progress battle-progress">
                                    <div id="p-hp-bar" class="progress-bar progress-bar-striped progress-bar-animated bg-success" style="width: 100%"></div>
                                </div>
                                <small class="d-flex justify-content-between mt-2">
                                    <span>EXP</span>
                                    <span><?= $pExp ?> / <?= $pNextExp ?></span>
                                </small>
                                <div class="progress exp-track">
                                    <div class="progress-bar bg-info" style="width: <?= round(($pExp / $pNextExp) * 100) ?>%"></div>
                                </div>
                            </div>
                            <div class="anima-panel">
                                <img id="player-sprite" src="<?= $playerImg ?>" class="anima-sprite" alt="Anima">
                                <div class="stat-popover">
                                    <div class="stat-row"><span class="stat-label">ATK</span> <span class="stat-val text-info"><?= e($playerAnima['attack']) ?></span></div>
                                    <div class="stat-row"><span class="stat-label">DEF</span> <span class="stat-val text-primary"><?= e($playerAnima['defense']) ?></span></div>
                                    <div class="stat-row"><span class="stat-label">SPD</span> <span class="stat-val text-secondary"><?= $pAtkSpd ?>ms</span></div>
                                </div>
                            </div>
                        </div>

                        <div class="col-6 battle-side">
                            <div class="battle-hud mb-2">
                                <div class="battle-name mb-1">
                                    <span id="e-name"><?= e($enemyAnima['species']) ?></span>
                                    <span class="badge badge-danger">Inimigo</span>
                                </div>
                                <small class="d-flex justify-content-between">
                                    <span>HP</span>
                                    <span id="e-hp-text">-</span>
                                </small>
                                <div class="progress battle-progress">
                                    <div id="e-hp-bar" class="progress-bar progress-bar-striped progress-bar-animated bg-success" style="width: 100%"></div>
                                </div>
                            </div>
                            <div class="anima-panel">
                                <img id="enemy-sprite" src="<?= $enemyImg ?>" class="anima-sprite" alt="Inimigo">
                                <div class="stat-popover">
                                    <div class="stat-row"><span class="stat-label">ATK</span> <span class="stat-val text-info"><?= e($enemyAnima['attack']) ?></span></div>
                                    <div class="stat-row"><span class="stat-label">DEF</span> <span class="stat-val text-primary"><?= e($enemyAnima['defense']) ?></span></div>
                                    <div class="stat-row"><span class="stat-label">SPD</span> <span class="stat-val text-secondary"><?= $eAtkSpd ?>ms</span></div>
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
        </div>

        <div class="col-12 col-lg-4 battle-log-col">
            <!-- Battle Log -->
            <div class="card card-outline card-secondary mb-0">
                <div class="card-header py-2">
                    <h3 class="card-title">Log de Batalha</h3>
                    <div class="card-tools">
                        <button type="button" class="btn btn-tool" data-card-widget="collapse" aria-label="Recolher log">
                            <i class="fas fa-minus"></i>
                        </button>
                    </div>
                </div>
                <div class="card-body p-0 bg-light">
                    <div class="term-body" id="battle-log"></div>
                </div>
            </div>
        </div>
    </div>
<?php
};

require __DIR__ . '/../_layout.php';
