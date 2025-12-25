<?php
defined('ABSPATH') || exit;

class JWT_API_Admin {
    private string $table;

    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'jwt_domains';
        add_action('admin_menu', [$this,'menu']);
        add_action('admin_enqueue_scripts', [$this,'assets']);
        add_action('admin_post_jwt_add', [$this,'add']);
        add_action('wp_ajax_jwt_regen', [$this,'regen']);
        add_action('wp_ajax_jwt_delete', [$this,'delete']);
    }

    public function assets() {
        wp_enqueue_script('tailwind-cdn','https://cdn.tailwindcss.com',[],null);
        wp_add_inline_script('tailwind-cdn', "
document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('jwt-modal');
    const modalText = document.getElementById('jwt-modal-text');
    const toast = document.getElementById('jwt-toast');
    const confirmBtn = document.getElementById('jwt-confirm-btn');
    const spinner = document.getElementById('jwt-spinner');

    let modalAction = null;
    let modalRow = null;

    document.body.addEventListener('click', e => {
        const btn = e.target.closest('[data-action]');
        if(!btn) return;

        const row = btn.closest('[data-row]');
        const key = row ? row.querySelector('[data-keybox]') : null;
        const id  = row ? row.dataset.id : null;

        switch(btn.dataset.action){
            case 'toggle':
                key.classList.toggle('is-visible');
                break;

            case 'copy':
                navigator.clipboard.writeText(key.dataset.real).then(() => {
                    toast.textContent = 'Clé copiée dans le presse-papier';
                    toast.classList.remove('hidden','opacity-0');
                    setTimeout(() => toast.classList.add('opacity-0'), 2000);
                    setTimeout(() => toast.classList.add('hidden'), 2500);
                });
                break;

            case 'regen':
            case 'delete':
                modalAction = btn.dataset.action;
                modalRow = id;
                modalText.textContent =
                    modalAction === 'regen'
                        ? 'Régénérer cette clé API ?'
                        : 'Supprimer définitivement cette clé API ?';
                modal.classList.remove('hidden');
                spinner.classList.add('hidden');
                confirmBtn.disabled = false;
                break;

            case 'confirm':
                confirmBtn.disabled = true;
                spinner.classList.remove('hidden');

                fetch(ajaxurl,{
                    method:'POST',
                    body:new URLSearchParams({
                        action:'jwt_' + modalAction,
                        id: modalRow
                    })
                }).then(() => location.reload());
                break;

            case 'cancel':
                modal.classList.add('hidden');
                break;
        }
    });
});
        ");
    }

    public function menu() {
        add_menu_page('JWT API Auth','JWT API Auth','manage_options','jwt-api-auth',[$this,'page']);
    }

    public function page() {
        global $wpdb;
        $rows = $wpdb->get_results("SELECT * FROM {$this->table}");
        ?>
<style>
[data-keybox]{
    display:block;width:100%;
    white-space:normal;
    word-break:break-word;
    overflow-wrap:anywhere;
}
[data-keybox]::before{content:'************';}
[data-keybox].is-visible::before{content:attr(data-real);}
</style>

<div id="jwt-toast"
     class="hidden fixed bottom-6 right-6 bg-green-600 text-white px-4 py-2 rounded shadow opacity-0 transition z-50">
</div>

<div id="jwt-modal"
     class="hidden fixed inset-0 bg-black/50 flex items-center justify-center z-50">
    <div class="bg-white rounded-lg p-6 max-w-md w-full">
        <p id="jwt-modal-text" class="mb-6 text-lg"></p>
        <div class="flex justify-end gap-4 items-center">
            <div id="jwt-spinner"
                 class="hidden mr-auto w-5 h-5 border-2 border-gray-300 border-t-blue-600 rounded-full animate-spin">
            </div>
            <button data-action="cancel"
                    class="px-4 py-2 rounded border">Annuler</button>
            <button id="jwt-confirm-btn"
                    data-action="confirm"
                    class="px-4 py-2 rounded bg-red-600 text-white disabled:opacity-50">
                Confirmer
            </button>
        </div>
    </div>
</div>

<div class="wrap w-full">
    <h1 class="text-3xl mb-6">JWT API Auth</h1>

    <!-- Add subdomain -->
    <div class="bg-white rounded shadow p-6 mb-8 max-w-3xl">
        <h2 class="text-xl font-semibold mb-4">Ajouter un sous-domaine</h2>
        <form method="post" action="<?= admin_url('admin-post.php'); ?>" class="flex gap-4 flex-wrap">
            <input type="hidden" name="action" value="jwt_add">
            <?php wp_nonce_field('jwt_add'); ?>
            <input type="text" name="domain" placeholder="api.example.com"
                   class="flex-1 min-w-[240px] border rounded px-3 py-2" required>
            <button class="bg-blue-600 text-white px-6 py-2 rounded">
                Ajouter
            </button>
        </form>
    </div>

    <!-- Keys -->
    <div class="grid lg:grid-cols-3 md:grid-cols-2 gap-6">
    <?php foreach($rows as $r): ?>
        <div data-row data-id="<?= $r->id ?>" class="bg-white rounded shadow p-5">
            <div class="font-mono mb-2 text-lg"><?= esc_html($r->domain) ?></div>
            <div data-keybox data-real="<?= esc_attr($r->api_key) ?>" class="mb-4"></div>

            <div class="flex justify-end gap-5 text-3xl">
                <button type="button" data-action="toggle">👁</button>
                <button type="button" data-action="copy">📋</button>
                <button type="button" data-action="regen">♻</button>
                <button type="button" data-action="delete">🗑</button>
            </div>
        </div>
    <?php endforeach; ?>
    </div>
</div>
<?php
    }

    public function add() {
        check_admin_referer('jwt_add');
        global $wpdb;
        $wpdb->insert($this->table,[
            'domain'=>sanitize_text_field($_POST['domain']),
            'api_key'=>bin2hex(random_bytes(32))
        ]);
        wp_redirect(admin_url('admin.php?page=jwt-api-auth'));
        exit;
    }

    public function regen() {
        global $wpdb;
        $wpdb->update($this->table,
            ['api_key'=>bin2hex(random_bytes(32))],
            ['id'=>(int)$_POST['id']]
        );
        wp_die();
    }

    public function delete() {
        global $wpdb;
        $wpdb->delete($this->table,['id'=>(int)$_POST['id']]);
        wp_die();
    }
}
