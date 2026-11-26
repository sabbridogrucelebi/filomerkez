import React, { useContext, useEffect, useRef, useState } from 'react';
import { AccessibilityInfo, ActivityIndicator, Alert, Animated, KeyboardAvoidingView, Platform, ScrollView, StyleSheet, Switch, Text, TextInput, TouchableOpacity, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import Ionicons from '@expo/vector-icons/Ionicons';
import api from '../api/axios';
import { AuthContext } from '../context/AuthContext';
import useStatusBarStyle from '../hooks/useStatusBarStyle';

const ENDPOINT = '/v1/telegram-kasko-settings';
const KASKO_CARD = { label: 'Kasko', icon: '🛡️', description: 'Kasko poliçesi bitişi', kind: 'date' };
const FIELDS = ['company_name', 'days_left', 'kasko_status', 'plate', 'brand_model', 'model_year', 'license_serial_no', 'chassis_no', 'license_owner', 'owner_tax_or_tc_no', 'policy_start_line', 'kasko_end_date'];

function configured(bot) {
    return bot?.has_bot_token && !!bot.chat_id;
}

function Input({ label, style, ...props }) {
    return (
        <View>
            <Text style={styles.label}>{label}</Text>
            <TextInput accessibilityLabel={label} placeholderTextColor="#64748B" style={[styles.input, style]} {...props} />
        </View>
    );
}

export default function TelegramKaskoSettingsScreen({ navigation }) {
    useStatusBarStyle('dark-content');
    const { hasPermission } = useContext(AuthContext);
    const canManage = hasPermission('telegram.manage');
    const [dashboard, setDashboard] = useState(null);
    const [channel, setChannel] = useState('kasko');
    const [form, setForm] = useState(null);
    const [loading, setLoading] = useState(true);
    const [working, setWorking] = useState(false);
    const [showTemplate, setShowTemplate] = useState(false);
    const [retryAt, setRetryAt] = useState({});
    const [clock, setClock] = useState(Date.now);
    const actionInProgress = useRef(false);
    const scroll = useRef(null);
    const settingsTop = useRef(0);
    const orbit = useRef(new Animated.Value(0)).current;

    useEffect(() => {
        let enabled = true;
        let animation;
        function motion(reduced) {
            if (!enabled) return;
            animation?.stop();
            orbit.setValue(0);
            if (reduced) return;
            animation = Animated.loop(Animated.sequence([
                Animated.timing(orbit, { toValue: 1, duration: 2100, useNativeDriver: true, isInteraction: false }),
                Animated.timing(orbit, { toValue: 0, duration: 2100, useNativeDriver: true, isInteraction: false }),
            ]));
            animation.start();
        }
        AccessibilityInfo.isReduceMotionEnabled().then(motion).catch(() => {});
        const subscription = AccessibilityInfo.addEventListener('reduceMotionChanged', motion);
        return () => {
            enabled = false;
            animation?.stop();
            subscription.remove();
        };
    }, [orbit]);

    function applyForm(card) {
        setForm({
            enabled: !!card.is_enabled,
            token: '',
            chat: card.chat_id || '',
            days: (card.reminder_days || []).join(', '),
            km: String(card.maintenance_km ?? 200),
            summary: card.upcoming_summary_enabled !== false,
            template: card.message_template || '',
        });
    }

    async function load() {
        setLoading(true);
        try {
            const response = await api.get(ENDPOINT);
            const data = response.data.data;
            setDashboard(data);
            applyForm(data.settings);
            const receivedAt = Date.now();
            setClock(receivedAt);
            setRetryAt(Object.fromEntries(Object.entries({ ...data.channels, kasko: data.settings })
                .map(([key, item]) => [key, receivedAt + Number(item.retry_after || 0) * 1000])));
        } catch (error) {
            Alert.alert('Telegram', error.response?.data?.message || 'Ayarlar yüklenemedi.');
        } finally {
            setLoading(false);
        }
    }

    useEffect(() => { if (canManage) load(); }, [canManage]);

    const update = (key, value) => setForm(previous => ({ ...previous, [key]: value }));
    const cards = dashboard ? { kasko: { ...KASKO_CARD, ...dashboard.settings }, ...dashboard.channels } : {};
    const bot = cards[channel];
    const check = dashboard?.automatic_checks?.[channel];
    const isKasko = channel === 'kasko';
    const isImm = channel === 'imm';
    const canEditSummary = isKasko || (isImm && dashboard?.imm_summary_ready);
    const endpoint = isKasko ? ENDPOINT : ENDPOINT + '/bots/' + channel;
    const ready = isKasko || dashboard?.fleet_ready;
    const canEditTemplate = isKasko || dashboard?.fleet_templates_ready;
    const count = Object.values(cards).filter(item => configured(item) && item.is_enabled).length;
    const testSeconds = Math.max(0, Math.ceil(((retryAt[channel] || 0) - clock) / 1000));
    const waiting = testSeconds > 0;

    useEffect(() => {
        if (!waiting) return;
        const timer = setInterval(() => setClock(Date.now()), 1000);
        return () => clearInterval(timer);
    }, [waiting]);

    function rememberCooldown(seconds) {
        const receivedAt = Date.now();
        setClock(receivedAt);
        setRetryAt(previous => ({ ...previous, [channel]: receivedAt + seconds * 1000 }));
    }

    function select(key) {
        if (working) return;
        setChannel(key);
        setShowTemplate(key !== 'kasko');
        applyForm(cards[key]);
        if (settingsTop.current > 0) scroll.current?.scrollTo({ y: settingsTop.current, animated: false });
    }

    async function save() {
        const token = form.token.trim();
        const chat = form.chat.trim();
        const days = form.days.split(/[\s,;]+/).filter(value => /^\d+$/.test(value)).map(Number)
            .filter(day => day >= 0 && day <= 365);
        const km = Number(form.km);
        if (form.enabled && (!(token || bot.has_bot_token) || !(chat || bot.chat_id))) {
            return Alert.alert('Bot bağlantısı', 'Bot anahtarı ve sohbet kimliğini girin.');
        }
        if (bot.kind === 'date' && !days.length) {
            return Alert.alert('Gün eşikleri', '0 ile 365 arasında en az bir gün girin.');
        }
        if (bot.kind === 'maintenance' && (!form.km.trim() || !Number.isInteger(km) || km < 0 || km > 100000)) {
            return Alert.alert('Bakım mesafesi', '0 ile 100.000 arasında bir kilometre değeri girin.');
        }
        if (isKasko && !form.template.trim()) {
            return Alert.alert('Mesaj şablonu', 'Kasko mesajını girin.');
        }
        if (!isKasko && ((token && !/^\d+:[A-Za-z0-9_-]+$/.test(token)) || (chat && !/^(?:-?\d+|@[A-Za-z0-9_]{5,})$/.test(chat)))) {
            return Alert.alert('Bot bağlantısı', 'Bot anahtarı veya sohbet kimliği biçimini kontrol edin.');
        }

        const payload = { is_enabled: form.enabled, chat_id: chat, ...(token ? { bot_token: token } : {}) };
        if (canEditTemplate) payload.message_template = form.template;
        if (canEditSummary) payload.upcoming_summary_enabled = form.summary;
        if (isKasko) {
            payload.reminder_days = days;
        } else if (bot.kind === 'date') {
            payload.reminder_days_text = form.days;
        } else if (bot.kind === 'maintenance') {
            payload.maintenance_km = km;
        }
        setWorking(true);
        try {
            const { data } = await api.put(endpoint, payload);
            setDashboard(previous => ({ ...previous, ...data.data }));
            const savedBot = isKasko ? data.data.settings : data.data.channels[channel];
            applyForm(savedBot);
            rememberCooldown(Number(savedBot.retry_after || 0));
            Alert.alert('Telegram', data.message);
        } catch (error) {
            const errors = error.response?.data?.errors;
            Alert.alert('Kaydedilemedi', (errors && Object.values(errors).flat()[0]) || error.response?.data?.message || 'Bot ayarları kaydedilemedi.');
        } finally {
            setWorking(false);
        }
    }

    async function action(name) {
        if (working || actionInProgress.current || (name === 'test' && waiting)) return;
        actionInProgress.current = true;
        setWorking(true);
        try {
            const { data } = await api.post(endpoint + '/' + name);
            Alert.alert('Telegram', data.message);
        } catch (error) {
            const response = error.response;
            if (name === 'test' && response?.status === 429) {
                const seconds = Number(response.data?.retry_after || response.headers?.['retry-after']);
                rememberCooldown(Number.isFinite(seconds) && seconds > 0 ? seconds : 60);
            } else {
                const title = name === 'test' && response?.status === 503 ? 'Telegram bağlantısı' : 'Telegram';
                Alert.alert(title, response?.data?.message || 'İşlem tamamlanamadı.');
            }
        } finally {
            actionInProgress.current = false;
            setWorking(false);
        }
    }

    return (
        <SafeAreaView style={styles.container}>
            <View style={styles.header}>
                <TouchableOpacity accessibilityRole="button" accessibilityLabel="Geri" onPress={() => navigation.goBack()} style={styles.back}>
                    <Ionicons name="arrow-back" size={24} color="#172A45" />
                </TouchableOpacity>
                <Text style={styles.title}>Telegram Uyarıları</Text>
            </View>
            {!canManage ? <Text style={styles.notice}>Telegram ayarlarını yönetme yetkiniz yok.</Text>
                : loading ? <ActivityIndicator style={styles.loader} color="#0369A1" />
                : !dashboard || !form ? <TouchableOpacity onPress={load} style={styles.secondary}><Text style={styles.secondaryText}>Yeniden yükle</Text></TouchableOpacity>
                : (
                    <KeyboardAvoidingView style={styles.container} behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
                        <ScrollView ref={scroll} contentContainerStyle={styles.content} keyboardShouldPersistTaps="handled">
                            <View style={styles.hero}>
                                <View style={styles.heroCopy}>
                                    <Text style={styles.heroTitle}>Telegram Uyarı Merkezi</Text>
                                    <Text style={styles.heroInfo}>{count} / {Object.keys(cards).length} bot etkin</Text>
                                    <Text style={styles.heroInfo}>Her gün {dashboard.schedule_time || '07:50'} · Türkiye saati</Text>
                                    {!!dashboard.scheduler_note && <Text style={styles.heroInfo}>{dashboard.scheduler_note}</Text>}
                                </View>
                                <Animated.View style={[styles.orb, { transform: [
                                    { perspective: 500 },
                                    { translateY: orbit.interpolate({ inputRange: [0, 1], outputRange: [4, -5] }) },
                                    { rotateY: orbit.interpolate({ inputRange: [0, 1], outputRange: ['-14deg', '14deg'] }) },
                                ] }]}>
                                    <Ionicons name="paper-plane" size={32} color="#FFFFFF" />
                                </Animated.View>
                            </View>

                            <View style={styles.grid}>
                                {Object.entries(cards).map(([key, item]) => (
                                    <TouchableOpacity key={key} accessibilityRole="button" accessibilityState={{ selected: channel === key }}
                                        disabled={working} onPress={() => select(key)} style={[styles.tile, channel === key && styles.selected]}>
                                        <Text style={styles.icon}>{item.icon}</Text>
                                        <Text style={styles.tileTitle}>{item.label}</Text>
                                        <Text style={[styles.muted, configured(item) && item.is_enabled && styles.enabled]}>
                                            {configured(item) ? (item.is_enabled ? 'Etkin' : 'Kapalı') : 'Kurulum gerekli'}
                                        </Text>
                                    </TouchableOpacity>
                                ))}
                            </View>

                            <View onLayout={event => { settingsTop.current = event.nativeEvent.layout.y; }} style={styles.panel}>
                                <Text style={styles.title}>{bot.icon} {bot.label}</Text>
                                <Text style={styles.muted}>{bot.description}</Text>
                                {check ? <Text style={styles.muted}>Son otomatik kontrol · {check.checked_at} · {check.summary}</Text>
                                    : !!dashboard.automatic_checks && bot.is_enabled && <Text style={styles.muted}>Bu hat için ilk otomatik kontrol bekleniyor.</Text>}
                                {!!bot.last_sent_at && <Text style={styles.muted}>Son otomatik uyarı · {bot.last_sent_at}</Text>}
                                {!ready ? <Text style={styles.notice}>Bu botu kurmak için web panelinden Telegram veritabanını güncelleyin.</Text> : (
                                    <>
                                        <TouchableOpacity accessibilityRole="button" disabled={working} onPress={save} style={styles.primary}>
                                            {working ? <ActivityIndicator color="#FFF" /> : <Text style={styles.primaryText}>Ayarları Kaydet</Text>}
                                        </TouchableOpacity>
                                        <TouchableOpacity accessibilityRole="button" disabled={working || waiting || !configured(bot)} onPress={() => action('test')}
                                            style={[styles.secondary, (waiting || !configured(bot)) && styles.disabled]}>
                                            <Text style={styles.secondaryText}>{waiting ? testSeconds + ' sn bekleyin' : (isKasko ? 'Tek Araç Testi' : isImm ? 'Kontrol Mesajı Gönder' : 'Örnek Mesaj Gönder')}</Text>
                                        </TouchableOpacity>
                                        {waiting && <Text style={styles.muted}>Telegram gönderim sınırı uyguladı. Sayaç bitince tekrar deneyin.</Text>}
                                        <View style={styles.toggle}>
                                            <Text style={styles.label}>Otomatik uyarılar etkin</Text>
                                            <Switch accessibilityLabel="Otomatik uyarılar etkin" value={form.enabled} onValueChange={value => update('enabled', value)} disabled={working} />
                                        </View>
                                        <Input label="Bot anahtarı" value={form.token} onChangeText={value => update('token', value)} secureTextEntry autoCapitalize="none" autoCorrect={false}
                                            maxLength={255} placeholder={bot.has_bot_token ? 'Kayıtlı · Yeni anahtarla değiştir' : 'BotFather anahtarı'} editable={!working} />
                                        <Input label="Sohbet / grup kimliği" value={form.chat} onChangeText={value => update('chat', value)} autoCapitalize="none" autoCorrect={false}
                                            maxLength={100} placeholder="Örn. -1001234567890" editable={!working} />
                                        {bot.kind === 'date' && (
                                            <>
                                                <Input label="Kalan gün eşikleri" value={form.days} onChangeText={value => update('days', value)} maxLength={500} editable={!working} />
                                                <Text style={styles.muted}>Günleri virgülle ayırın. Kaçan eşikte geçerli son eşik bir kez bildirilir.</Text>
                                            </>
                                        )}
                                        {bot.kind === 'maintenance' && <Input label="Bakım uyarı mesafesi (km)" value={form.km} onChangeText={value => update('km', value)} keyboardType="number-pad" editable={!working} />}
                                        {bot.kind === 'fuel' && <Text style={styles.muted}>Bugünün ve dünün yakıt alımları araçta kayıtlı minimum km/litre sınırıyla karşılaştırılır.</Text>}
                                        {canEditSummary && (
                                            <View style={styles.toggle}>
                                                <View style={styles.heroCopy}>
                                                    <Text style={styles.label}>Günlük yaklaşan {isKasko ? 'kasko' : 'İMM'} özeti</Text>
                                                    <Text style={styles.muted}>Aralığı en büyük eşik belirler. Yenileme yüklenince yeni bitiş tarihi kullanılır.</Text>
                                                </View>
                                                <Switch accessibilityLabel={isKasko ? 'Günlük yaklaşan kasko özeti' : 'Günlük yaklaşan İMM özeti'} value={form.summary} onValueChange={value => update('summary', value)} disabled={working} />
                                            </View>
                                        )}
                                        {isImm && !canEditSummary && <Text style={styles.notice}>Günlük İMM özeti için web panelinden Telegram veritabanını güncelleyin.</Text>}
                                        <TouchableOpacity accessibilityRole="button" accessibilityState={{ expanded: showTemplate }} onPress={() => setShowTemplate(value => !value)} style={styles.secondary}>
                                            <Text style={styles.secondaryText}>Telegram mesaj şablonu {showTemplate ? '−' : '+'}</Text>
                                        </TouchableOpacity>
                                        {showTemplate && (
                                            <>
                                                <Input label="Mesaj metni" value={form.template} onChangeText={value => update('template', value)}
                                                    multiline textAlignVertical="top" maxLength={isKasko ? 4000 : 3000} style={styles.template} editable={!working && canEditTemplate} />
                                                {canEditTemplate ? (
                                                    <>
                                                        <Text style={styles.muted}>Alanlar araç bilgileriyle otomatik dolar. {!isKasko && 'Hazır şablona dönmek için metni silip kaydedin.'}</Text>
                                                        {isImm && <Text style={styles.muted}>Kontrol mesajı en büyük eşiğe giren İMM poliçelerini toplu listeler.</Text>}
                                                        <Text style={styles.muted}>{(bot.template_fields || FIELDS).map(field => '{{' + field + '}}').join('  ')}</Text>
                                                    </>
                                                ) : <Text style={styles.notice}>Şablonu düzenlemek için web panelinden Telegram veritabanını güncelleyin.</Text>}
                                            </>
                                        )}
                                        {(bot.assistant_examples || []).length > 0 && (
                                            <>
                                                <Text style={styles.label}>Telegram’da soru sor</Text>
                                                {bot.assistant_examples.map(example => <Text key={example} style={styles.muted}>• {example}</Text>)}
                                            </>
                                        )}
                                        {configured(bot) && (
                                            <>
                                                <TouchableOpacity accessibilityRole="button" disabled={working} onPress={() => action('connect')} style={styles.secondary}>
                                                    <Text style={styles.secondaryText}>Grup komutlarını bağla</Text>
                                                </TouchableOpacity>
                                                <Text style={styles.muted}>Sorunuzu bağlı gruba yazın. Grup gizliliği açıksa sorunun başına /sor@BotKullanıcıAdı ekleyin.</Text>
                                                {isKasko && <Text style={styles.muted}>Diğer grup komutları · kaskolu araçlarım · süresi geçenler · kaskosu olmayan araçlar</Text>}
                                            </>
                                        )}
                                    </>
                                )}
                            </View>
                        </ScrollView>
                    </KeyboardAvoidingView>
                )}
        </SafeAreaView>
    );
}

const styles = StyleSheet.create({
    container: { flex: 1, backgroundColor: '#F2F6FB' },
    header: { flexDirection: 'row', alignItems: 'center', gap: 12, padding: 14, backgroundColor: '#FFFFFF' },
    back: { backgroundColor: '#EAF1F8', borderRadius: 12, padding: 8 },
    title: { color: '#172A45', fontWeight: '900', fontSize: 19 },
    loader: { marginTop: 40 },
    content: { padding: 16, paddingBottom: 36 },
    hero: { borderRadius: 23, padding: 18, backgroundColor: '#11294B', flexDirection: 'row', alignItems: 'center', gap: 14, marginBottom: 16 },
    heroCopy: { flex: 1 },
    heroTitle: { color: '#FFFFFF', fontSize: 21, fontWeight: '900' },
    heroInfo: { color: '#C9E6FB', fontSize: 12, marginTop: 6 },
    orb: { width: 60, height: 60, borderRadius: 19, backgroundColor: '#167CC7', borderBottomWidth: 5, borderColor: '#10518E', alignItems: 'center', justifyContent: 'center', elevation: 5 },
    grid: { flexDirection: 'row', flexWrap: 'wrap', justifyContent: 'space-between', rowGap: 9, marginBottom: 16 },
    tile: { width: '48.7%', padding: 13, gap: 4, backgroundColor: '#FFFFFF', borderWidth: 1, borderColor: '#D7E4EF', borderRadius: 16, elevation: 2 },
    selected: { borderColor: '#117ABB', backgroundColor: '#EAF7FF' },
    icon: { fontSize: 20 },
    tileTitle: { color: '#172A45', fontWeight: '800', fontSize: 14 },
    enabled: { color: '#047857', fontWeight: '800' },
    muted: { color: '#475569', fontSize: 12, lineHeight: 17 },
    notice: { color: '#92400E', backgroundColor: '#FFF4D6', borderRadius: 10, padding: 12, marginTop: 10 },
    panel: { gap: 11, padding: 17, borderRadius: 21, backgroundColor: '#FFFFFF', borderWidth: 1, borderColor: '#D7E4EF' },
    label: { fontSize: 13, fontWeight: '800', color: '#172A45' },
    input: { marginTop: 6, borderWidth: 1, borderColor: '#B8CDE0', borderRadius: 12, paddingHorizontal: 12, minHeight: 46, color: '#172A45' },
    template: { minHeight: 220, paddingVertical: 10 },
    toggle: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: 8, backgroundColor: '#EEF4FA', paddingHorizontal: 11, paddingVertical: 6, borderRadius: 12 },
    primary: { alignItems: 'center', backgroundColor: '#0369A1', borderRadius: 12, padding: 13 },
    primaryText: { color: '#FFFFFF', fontWeight: '800' },
    secondary: { alignItems: 'center', backgroundColor: '#EAF4FC', borderRadius: 12, padding: 12 },
    disabled: { opacity: 0.55 },
    secondaryText: { color: '#075985', fontWeight: '800' },
});
