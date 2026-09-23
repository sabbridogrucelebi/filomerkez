// Arayüz ikonları (3D emoji görselleri) uygulama içinden gelir.
// Daha önce raw.githubusercontent.com'dan indiriliyorlardı; internet yokken,
// GitHub erişilemezken veya rate-limit'e girildiğinde menü ve KPI kartları
// ikonsuz görünüyordu. Görseller tek kareye düzleştirilmiş 256px PNG'ler.
//
// Anahtarlar küçük harfe çevrilmiş "kategori/isim" biçimindedir.
const ICONS = {
    'activities/artist palette': require('../assets/emoji/artist_palette.png'),
    'activities/party popper': require('../assets/emoji/party_popper.png'),
    'food and drink/birthday cake': require('../assets/emoji/birthday_cake.png'),
    'objects/briefcase': require('../assets/emoji/briefcase.png'),
    'objects/chart increasing': require('../assets/emoji/chart_increasing.png'),
    'objects/chart increasing with yen': require('../assets/emoji/chart_increasing_with_yen.png'),
    'objects/file cabinet': require('../assets/emoji/file_cabinet.png'),
    'objects/fountain pen': require('../assets/emoji/fountain_pen.png'),
    'objects/framed picture': require('../assets/emoji/framed_picture.png'),
    'objects/gear': require('../assets/emoji/gear.png'),
    'objects/hammer and wrench': require('../assets/emoji/hammer_and_wrench.png'),
    'objects/magnifying glass tilted left': require('../assets/emoji/magnifying_glass_tilted_left.png'),
    'objects/money bag': require('../assets/emoji/money_bag.png'),
    'objects/money with wings': require('../assets/emoji/money_with_wings.png'),
    'objects/open book': require('../assets/emoji/open_book.png'),
    'objects/outbox tray': require('../assets/emoji/outbox_tray.png'),
    'objects/page facing up': require('../assets/emoji/page_facing_up.png'),
    'objects/rolled-up newspaper': require('../assets/emoji/rolled_up_newspaper.png'),
    'objects/shield': require('../assets/emoji/shield.png'),
    'objects/tear-off calendar': require('../assets/emoji/tear_off_calendar.png'),
    'people/construction worker': require('../assets/emoji/construction_worker.png'),
    'people/man pilot': require('../assets/emoji/man_pilot.png'),
    'symbols/check mark button': require('../assets/emoji/check_mark_button.png'),
    'symbols/cross mark': require('../assets/emoji/cross_mark.png'),
    'symbols/prohibited': require('../assets/emoji/prohibited.png'),
    'symbols/warning': require('../assets/emoji/warning.png'),
    'travel and places/automobile': require('../assets/emoji/automobile.png'),
    'travel and places/fuel pump': require('../assets/emoji/fuel_pump.png'),
    'travel and places/high-speed train': require('../assets/emoji/high_speed_train.png'),
    'travel and places/house': require('../assets/emoji/house.png'),
    'travel and places/minibus': require('../assets/emoji/minibus.png'),
    'travel and places/office building': require('../assets/emoji/office_building.png'),
    'travel and places/oncoming automobile': require('../assets/emoji/oncoming_automobile.png'),
    'travel and places/oncoming bus': require('../assets/emoji/oncoming_bus.png'),
    'travel and places/police car light': require('../assets/emoji/police_car_light.png'),
    'travel and places/seat': require('../assets/emoji/seat.png'),
};

const FALLBACK = ICONS['objects/framed picture'];

// key: 'Objects/Gear' veya URL kodlu 'Travel%20and%20places/House' olabilir.
export const emoji = (key) => {
    if (!key) return FALLBACK;
    let normalized;
    try {
        normalized = decodeURIComponent(key);
    } catch {
        normalized = key;
    }
    return ICONS[normalized.toLowerCase()] || FALLBACK;
};

export default emoji;
