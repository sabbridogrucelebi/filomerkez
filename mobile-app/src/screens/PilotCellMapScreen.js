import React from 'react';
import { View, Text, StyleSheet, TouchableOpacity, Platform } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import MaterialCommunityIcons from '@expo/vector-icons/MaterialCommunityIcons';
import { MapView, Marker } from '../components/MapProxy';
import SpaceWaves from '../components/SpaceWaves';

export default function PilotCellMapScreen({ route, navigation }) {
    const { student } = route.params;

    const hasMorning = student.morning_lat && student.morning_lng;
    const hasEvening = student.evening_lat && student.evening_lng;

    // Define coordinates
    const morningCoord = hasMorning ? { latitude: parseFloat(student.morning_lat), longitude: parseFloat(student.morning_lng) } : null;
    const eveningCoord = hasEvening ? { latitude: parseFloat(student.evening_lat), longitude: parseFloat(student.evening_lng) } : null;

    const renderMap = (coord, title, color, titlePrefix) => {
        if (!coord) {
            return (
                <View style={styles.emptyMapContainer}>
                    <MaterialCommunityIcons name="map-marker-off" size={48} color="#475569" />
                    <Text style={styles.emptyMapText}>{titlePrefix} kaydedilmemiş.</Text>
                </View>
            );
        }

        return (
            <View style={styles.mapWrapper}>
                <View style={styles.mapTitleContainer}>
                    <MaterialCommunityIcons name="map-marker" size={18} color={color} />
                    <Text style={styles.mapTitle}>{title}</Text>
                </View>
                <MapView
                    style={styles.map}
                    initialRegion={{
                        ...coord,
                        latitudeDelta: 0.005,
                        longitudeDelta: 0.005,
                    }}
                >
                    <Marker coordinate={coord} title={title} pinColor={color} />
                </MapView>
            </View>
        );
    };

    return (
        <SafeAreaView style={styles.container}>
            <SpaceWaves />
            <View style={styles.header}>
                <TouchableOpacity style={styles.backBtn} onPress={() => navigation.goBack()}>
                    <MaterialCommunityIcons name="arrow-left" size={26} color="#FFF" />
                </TouchableOpacity>
                <Text style={styles.headerTitle} numberOfLines={1}>{student.name} Konumları</Text>
                <View style={{ width: 34 }} />
            </View>

            <View style={styles.mapsContainer}>
                {/* Morning Map (Top) */}
                <View style={styles.halfScreen}>
                    {renderMap(morningCoord, 'Sabah Konumu (Biniş)', '#0284C7', 'Sabah konumu')}
                </View>

                <View style={styles.divider} />

                {/* Evening Map (Bottom) */}
                <View style={styles.halfScreen}>
                    {renderMap(eveningCoord, 'Akşam Konumu (İniş)', '#8B5CF6', 'Akşam konumu')}
                </View>
            </View>
        </SafeAreaView>
    );
}

const styles = StyleSheet.create({
    container: { flex: 1, backgroundColor: '#020617' },
    header: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', paddingHorizontal: 20, paddingTop: 10, paddingBottom: 15, zIndex: 10 },
    backBtn: { padding: 8, backgroundColor: 'rgba(255,255,255,0.1)', borderRadius: 12 },
    headerTitle: { fontSize: 18, fontWeight: '900', color: '#FFF', letterSpacing: 0.5, flex: 1, textAlign: 'center', marginHorizontal: 10 },
    mapsContainer: { flex: 1, flexDirection: 'column', backgroundColor: 'transparent', marginHorizontal: 16, marginBottom: 16, borderRadius: 24, overflow: 'hidden', borderWidth: 1, borderColor: 'rgba(255,255,255,0.1)' },
    halfScreen: { flex: 1, backgroundColor: '#0F172A' },
    divider: { height: 4, backgroundColor: 'rgba(255,255,255,0.1)' },
    mapWrapper: { flex: 1 },
    mapTitleContainer: { flexDirection: 'row', alignItems: 'center', position: 'absolute', top: 16, left: 16, right: 16, backgroundColor: 'rgba(15, 23, 42, 0.95)', padding: 12, borderRadius: 12, zIndex: 10, borderWidth: 1, borderColor: 'rgba(255,255,255,0.1)', shadowColor: '#000', shadowOffset: { width: 0, height: 4 }, shadowopacity: 1, shadowRadius: 8, elevation: 5 },
    mapTitle: { fontSize: 15, fontWeight: '800', color: '#FFF', marginLeft: 8, letterSpacing: 0.5 },
    map: { flex: 1 },
    emptyMapContainer: { flex: 1, justifyContent: 'center', alignItems: 'center', backgroundColor: '#0F172A' },
    emptyMapText: { marginTop: 16, fontSize: 15, color: '#94A3B8', fontWeight: '600' }
});
